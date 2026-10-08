<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\ModuleQuiz;
use App\Models\ModuleQuizAnswer;
use App\Models\ModuleQuizAttempt;
use App\Models\ScholarUser;
use App\Services\ModuleAccessService;

class ModuleQuizAttemptController extends Controller
{
    public function index(Request $request)
    {
        try {
            $userId = $request->user()->id;

            $query = ModuleQuizAttempt::with('quiz')
                ->whereHas('enrollment', function ($q) use ($userId) {
                    $q->where('learner_id', $userId);
                });

            if ($quizId = trim($request->input('quiz_id', ''))) {
                $query->where('quiz_id', $quizId);
            }

            if ($enrollmentId = trim($request->input('enrollment_id', ''))) {
                $query->where('enrollment_id', $enrollmentId);
            }

            $results = $query->orderBy('attempt_number', 'desc')->paginate(20);

            return response()->json([
                'status' => 200,
                'data'   => $results,
            ], 200);
        } catch (\Exception $e) {
            Log::error('ModuleQuizAttemptController@index: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving quiz attempts.',
            ], 500);
        }
    }

    /**
     * Starts (or resumes) an attempt at a module quiz for the caller's own
     * enrollment. Enforces: enrollment must be active, module must already be
     * unlocked and the previous module passed, max attempts and 24h cooldown
     * between attempts.
     */
    public function start(Request $request)
    {
        $enrollmentId = $request->input('enrollment_id');
        $quizId = $request->input('quiz_id');

        if (!$enrollmentId || !$quizId) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => ['enrollment_id' => ['enrollment_id and quiz_id are required.']],
            ], 422);
        }

        try {
            $enrollment = Enrollment::find($enrollmentId);
            $quiz = ModuleQuiz::with('module')->find($quizId);

            if (!$enrollment || !$quiz) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Enrollment or module quiz not found.',
                ], 404);
            }

            if ((string) $enrollment->learner_id !== (string) $request->user()->id) {
                return response()->json([
                    'status'  => 403,
                    'message' => 'Forbidden.',
                ], 403);
            }

            if ($enrollment->enrollment_status === 'failed') {
                return response()->json([
                    'status'  => 403,
                    'message' => 'This course has been marked as failed. Ask an admin to reset your access before retrying.',
                ], 403);
            }

            if ($quiz->admin_approval_status !== 'approved') {
                return response()->json([
                    'status'  => 403,
                    'message' => 'This quiz is currently under review and is not available yet.',
                ], 403);
            }

            $accessCheck = ModuleAccessService::checkModuleAccess($enrollment, $quiz->module);
            if (!$accessCheck['accessible']) {
                return response()->json([
                    'status'  => 403,
                    'message' => $accessCheck['reason'],
                ], 403);
            }

            $totalAttempts = ModuleQuizAttempt::where('quiz_id', $quiz->id)
                ->where('enrollment_id', $enrollment->id)
                ->count();

            // Only submitted attempts count against the limit - matches submit()'s
            // gating below, so an attempt a learner started but never finished
            // (e.g. they navigated away) doesn't silently burn one of their tries.
            $submittedAttempts = ModuleQuizAttempt::where('quiz_id', $quiz->id)
                ->where('enrollment_id', $enrollment->id)
                ->whereNotNull('submitted_at')
                ->count();

            if ($submittedAttempts >= $quiz->max_attempts) {
                return response()->json([
                    'status'  => 403,
                    'message' => 'You have used all available attempts for this quiz.',
                ], 403);
            }

            $lastAttempt = ModuleQuizAttempt::where('quiz_id', $quiz->id)
                ->where('enrollment_id', $enrollment->id)
                ->orderBy('attempt_number', 'desc')
                ->first();

            if ($lastAttempt && $lastAttempt->submitted_at && !$lastAttempt->passed) {
                $availableAt = $lastAttempt->submitted_at->copy()->addHours($quiz->cooldown_hours);
                if (now()->lt($availableAt)) {
                    return response()->json([
                        'status'  => 403,
                        'message' => "You can retry this quiz after {$availableAt->toDateTimeString()}.",
                    ], 403);
                }
            }

            $attempt = ModuleQuizAttempt::create([
                'quiz_id' => $quiz->id,
                'enrollment_id' => $enrollment->id,
                'attempt_number' => $totalAttempts + 1,
                'started_at' => now(),
            ]);

            return response()->json([
                'status'  => 201,
                'message' => 'Quiz attempt started.',
                'data'    => $attempt,
            ], 201);
        } catch (\Exception $e) {
            Log::error('ModuleQuizAttemptController@start: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while starting the quiz attempt.',
            ], 500);
        }
    }

    /**
     * Submits answers for an in-progress attempt, grades it, and on the final
     * allowed failed attempt marks the enrollment as failed at that module.
     */
    public function submit(Request $request, string $id)
    {
        $answers = $request->input('answers', []);

        if (!is_array($answers) || count($answers) === 0) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => ['answers' => ['At least one answer is required.']],
            ], 422);
        }

        try {
            $attempt = ModuleQuizAttempt::with(['quiz.questions', 'enrollment'])->find($id);

            if (!$attempt) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Quiz attempt not found.',
                ], 404);
            }

            if ((string) $attempt->enrollment->learner_id !== (string) $request->user()->id) {
                return response()->json([
                    'status'  => 403,
                    'message' => 'Forbidden.',
                ], 403);
            }

            if ($attempt->submitted_at) {
                return response()->json([
                    'status'  => 409,
                    'message' => 'This attempt has already been submitted.',
                ], 409);
            }

            $questions = $attempt->quiz->questions;
            $correctCount = 0;

            foreach ($questions as $question) {
                $selected = $answers[$question->id] ?? null;
                $isCorrect = $selected !== null && $selected === $question->correct_option_key;

                if ($isCorrect) {
                    $correctCount++;
                }

                ModuleQuizAnswer::create([
                    'attempt_id' => $attempt->id,
                    'question_id' => $question->id,
                    'selected_option_key' => $selected,
                    'is_correct' => $isCorrect,
                ]);
            }

            $totalQuestions = $questions->count();
            $scorePercent = $totalQuestions > 0 ? (int) round(($correctCount / $totalQuestions) * 100) : 0;
            $passed = $scorePercent >= $attempt->quiz->passing_percent;

            $attempt->update([
                'score_percent' => $scorePercent,
                'passed' => $passed,
                'submitted_at' => now(),
            ]);

            $enrollment = $attempt->enrollment;

            if ($passed) {
                if ($enrollment->failed_module_id === $attempt->quiz->module_id) {
                    $enrollment->update(['enrollment_status' => 'active', 'failed_module_id' => null]);
                }
            } else {
                $attemptsUsed = ModuleQuizAttempt::where('quiz_id', $attempt->quiz_id)
                    ->where('enrollment_id', $enrollment->id)
                    ->whereNotNull('submitted_at')
                    ->count();

                if ($attemptsUsed >= $attempt->quiz->max_attempts) {
                    $enrollment->update([
                        'enrollment_status' => 'failed',
                        'failed_module_id' => $attempt->quiz->module_id,
                    ]);
                }
            }

            return response()->json([
                'status'  => 200,
                'message' => $passed ? 'Quiz passed.' : 'Quiz not passed.',
                'data'    => $attempt->fresh(['answers']),
            ], 200);
        } catch (\Exception $e) {
            Log::error('ModuleQuizAttemptController@submit: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while submitting the quiz attempt.',
            ], 500);
        }
    }

    public function show(Request $request, string $id)
    {
        try {
            $attempt = ModuleQuizAttempt::with(['quiz', 'answers.question', 'enrollment'])->find($id);

            if (!$attempt) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Quiz attempt not found.',
                ], 404);
            }

            $user = $request->scholarUser();
            $isAdmin = $user && $user->isAdmin();
            $isOwner = (string) $attempt->enrollment->learner_id === (string) $request->user()->id;

            if (!$isAdmin && !$isOwner) {
                return response()->json([
                    'status'  => 403,
                    'message' => 'Forbidden.',
                ], 403);
            }

            return response()->json([
                'status' => 200,
                'data'   => $attempt,
            ], 200);
        } catch (\Exception $e) {
            Log::error('ModuleQuizAttemptController@show: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving the quiz attempt.',
            ], 500);
        }
    }
}

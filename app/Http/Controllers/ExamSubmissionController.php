<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Helpers\Validations;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamAnswer;
use App\Models\ExamSubmission;
use App\Models\ScholarUser;
use App\Traits\AuthorizesCourseOwnership;

class ExamSubmissionController extends Controller
{
    use AuthorizesCourseOwnership;

    public function index(Request $request)
    {
        try {
            $user = ScholarUser::find($request->user()->id);

            $query = ExamSubmission::with('exam.course');

            if (!$user || $user->role === 'student') {
                $query->where('learner_id', $request->user()->id);
            }

            if ($examId = trim($request->input('exam_id', ''))) {
                $query->where('exam_id', $examId);

                if ($user && $user->role === 'instructor') {
                    $query->whereHas('exam.course', function ($q) use ($request) {
                        $q->manageableBy($request->user()->id);
                    });
                }
            }

            $results = $query->orderBy('created_at', 'desc')->paginate(10);

            return response()->json([
                'status' => 200,
                'data'   => $results,
            ], 200);
        } catch (\Exception $e) {
            Log::error('ExamSubmissionController@index: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving exam submissions.',
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $validator = Validations::validateExamSubmission($request->all());

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => $validator->messages(),
            ], 422);
        }

        try {
            $user = ScholarUser::find($request->user()->id);
            $isElevated = $user && in_array($user->role, ['instructor', 'admin']);

            $data = $request->all();
            $exam = Exam::find($data['exam_id'] ?? null);

            if (!$isElevated) {
                unset($data['score'], $data['status']);
                $data['learner_id'] = $request->user()->id;

                if (!$exam) {
                    return response()->json([
                        'status'  => 404,
                        'message' => 'Exam not found.',
                    ], 404);
                }

                if ($exam->admin_approval_status !== 'approved') {
                    return response()->json([
                        'status'  => 403,
                        'message' => 'This exam is currently under review and is not available yet.',
                    ], 403);
                }

                $isEnrolled = Enrollment::where('course_id', $exam->course_id)
                    ->where('learner_id', $request->user()->id)
                    ->exists();

                if (!$isEnrolled) {
                    return response()->json([
                        'status'  => 403,
                        'message' => 'You must be enrolled in this course to take this exam.',
                    ], 403);
                }

                $attemptsUsed = ExamSubmission::where('exam_id', $exam->id)
                    ->where('learner_id', $request->user()->id)
                    ->count();

                if ($attemptsUsed >= ($exam->attempts_allowed ?? 1)) {
                    return response()->json([
                        'status'  => 403,
                        'message' => 'You have used all available attempts for this exam.',
                    ], 403);
                }

                $data['attempt_number'] = $attemptsUsed + 1;
            }

            $data['status'] = $data['status'] ?? 'in_progress';
            $data['started_at'] = $data['started_at'] ?? now();

            $examSubmission = ExamSubmission::create($data);

            $examSubmission->refresh();

            return response()->json([
                'status'  => 201,
                'message' => 'Exam submission created successfully.',
                'data'    => $examSubmission,
            ], 201);
        } catch (\Exception $e) {
            Log::error('ExamSubmissionController@store: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while creating the exam submission.',
            ], 500);
        }
    }

    public function show(Request $request, string $id)
    {
        try {
            $user = ScholarUser::find($request->user()->id);
            $examSubmission = ExamSubmission::with(['exam.course', 'answers.question'])->find($id);

            if (!$examSubmission) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Exam submission not found.',
                ], 404);
            }

            $isElevated = $user && in_array($user->role, ['instructor', 'admin']);
            $isOwner = (string) $examSubmission->learner_id === (string) $request->user()->id;

            if (!$isElevated && !$isOwner) {
                return response()->json([
                    'status'  => 403,
                    'message' => 'Forbidden.',
                ], 403);
            }

            return response()->json([
                'status' => 200,
                'data'   => $examSubmission,
            ], 200);
        } catch (\Exception $e) {
            Log::error('ExamSubmissionController@show: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving the exam submission.',
            ], 500);
        }
    }

    public function update(Request $request, string $id)
    {
        $validator = Validations::validateExamSubmission($request->all());

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => $validator->messages(),
            ], 422);
        }

        try {
            $user = ScholarUser::find($request->user()->id);
            $examSubmission = ExamSubmission::find($id);

            if (!$examSubmission) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Exam submission not found.',
                ], 404);
            }

            $isElevated = $user && in_array($user->role, ['instructor', 'admin']);
            $isOwner = (string) $examSubmission->learner_id === (string) $request->user()->id;

            if (!$isElevated && !$isOwner) {
                return response()->json([
                    'status'  => 403,
                    'message' => 'Forbidden.',
                ], 403);
            }

            $data = $request->all();

            if (!$isElevated) {
                unset($data['score'], $data['status'], $data['learner_id']);
            }

            $examSubmission->update($data);

            return response()->json([
                'status'  => 200,
                'message' => 'Exam submission updated successfully.',
                'data'    => $examSubmission,
            ], 200);
        } catch (\Exception $e) {
            Log::error('ExamSubmissionController@update: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while updating the exam submission.',
            ], 500);
        }
    }

    public function delete(Request $request, string $id)
    {
        try {
            $user = ScholarUser::find($request->user()->id);

            if (!$user || !in_array($user->role, ['instructor', 'admin'])) {
                return response()->json([
                    'status'  => 403,
                    'message' => 'Forbidden.',
                ], 403);
            }

            $examSubmission = ExamSubmission::find($id);

            if (!$examSubmission) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Exam submission not found.',
                ], 404);
            }

            $examSubmission->delete();

            return response()->json([
                'status'  => 200,
                'message' => 'Exam submission deleted successfully.',
            ], 200);
        } catch (\Exception $e) {
            Log::error('ExamSubmissionController@delete: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while deleting the exam submission.',
            ], 500);
        }
    }

    /**
     * Submits the learner's answers for an in-progress submission. mcq/true_false
     * questions are auto-graded immediately; short_answer/essay questions are left
     * ungraded (marks_awarded = null) for an instructor to grade via grade() below.
     * The submission is marked 'graded' outright only if every question was
     * auto-gradable, otherwise 'submitted' pending manual grading.
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
            $submission = ExamSubmission::with('exam.questions')->find($id);

            if (!$submission) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Exam submission not found.',
                ], 404);
            }

            if ((string) $submission->learner_id !== (string) $request->user()->id) {
                return response()->json([
                    'status'  => 403,
                    'message' => 'Forbidden.',
                ], 403);
            }

            if ($submission->submitted_at) {
                return response()->json([
                    'status'  => 409,
                    'message' => 'This submission has already been submitted.',
                ], 409);
            }

            $answersByQuestionId = collect($answers)->keyBy('question_id');
            $needsManualGrading = false;
            $totalScore = 0;

            foreach ($submission->exam->questions as $question) {
                $answerGiven = $answersByQuestionId[$question->id]['answer_given'] ?? null;
                $isAutoGradable = in_array($question->question_type, ['mcq', 'true_false'], true);

                $marksAwarded = null;
                $isCorrect = null;

                if ($isAutoGradable) {
                    $isCorrect = $answerGiven !== null && $answerGiven === $question->correct_answer;
                    $marksAwarded = $isCorrect ? $question->marks : 0;
                    $totalScore += $marksAwarded;
                } else {
                    $needsManualGrading = true;
                }

                ExamAnswer::create([
                    'submission_id' => $submission->id,
                    'question_id' => $question->id,
                    'answer_given' => $answerGiven,
                    'marks_awarded' => $marksAwarded,
                    'is_correct' => $isCorrect,
                ]);
            }

            $submission->update([
                'score' => $totalScore,
                'status' => $needsManualGrading ? 'submitted' : 'graded',
                'submitted_at' => now(),
            ]);

            return response()->json([
                'status'  => 200,
                'message' => $needsManualGrading ? 'Exam submitted and awaiting manual grading.' : 'Exam submitted and graded.',
                'data'    => $submission->fresh(['answers.question']),
            ], 200);
        } catch (\Exception $e) {
            Log::error('ExamSubmissionController@submit: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while submitting the exam.',
            ], 500);
        }
    }

    /**
     * Instructor/admin manual grading for short_answer/essay answers that
     * submit() couldn't auto-grade. Recomputes the submission's total score
     * from all of its answers (auto-graded + freshly manually-graded) and
     * marks it 'graded'.
     */
    public function grade(Request $request, string $id)
    {
        $answers = $request->input('answers', []);

        if (!is_array($answers) || count($answers) === 0) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => ['answers' => ['At least one graded answer is required.']],
            ], 422);
        }

        try {
            $submission = ExamSubmission::with(['exam.course', 'answers'])->find($id);

            if (!$submission) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Exam submission not found.',
                ], 404);
            }

            if (!$this->canManageCourse($request, $submission->exam?->course)) {
                return response()->json([
                    'status'  => 403,
                    'message' => 'Forbidden.',
                ], 403);
            }

            foreach ($answers as $graded) {
                if (empty($graded['answer_id'])) {
                    continue;
                }

                ExamAnswer::where('id', $graded['answer_id'])
                    ->where('submission_id', $submission->id)
                    ->update([
                        'marks_awarded' => $graded['marks_awarded'] ?? null,
                        'is_correct' => $graded['is_correct'] ?? null,
                    ]);
            }

            $totalScore = ExamAnswer::where('submission_id', $submission->id)->sum('marks_awarded');

            $submission->update([
                'score' => $totalScore,
                'status' => 'graded',
            ]);

            return response()->json([
                'status'  => 200,
                'message' => 'Exam submission graded successfully.',
                'data'    => $submission->fresh(['answers.question']),
            ], 200);
        } catch (\Exception $e) {
            Log::error('ExamSubmissionController@grade: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while grading the exam submission.',
            ], 500);
        }
    }
}

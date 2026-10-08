<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Helpers\Validations;
use App\Models\Exam;
use App\Models\ExamQuestion;
use App\Models\ScholarUser;
use App\Services\NotificationService;
use App\Traits\AuthorizesCourseOwnership;

class ExamQuestionController extends Controller
{
    use AuthorizesCourseOwnership;

    public function index(Request $request)
    {
        try {
            $user = $request->scholarUser();

            $query = ExamQuestion::query();

            if ($examId = trim($request->input('exam_id', ''))) {
                $query->where('exam_id', $examId);
            }

            if ($user && $user->role === 'instructor') {
                $query->whereHas('exam.course', function ($q) use ($request) {
                    $q->manageableBy($request->user()->id);
                });
            } elseif ($user && $user->role === 'student') {
                // Same visibility rule as ExamController@index - a student only ever
                // sees questions for a live (approved) exam on a course they're
                // actually enrolled in, never someone else's question bank.
                $query->whereHas('exam', function ($q) use ($request) {
                    $q->where('admin_approval_status', 'approved')
                        ->whereHas('course.enrollments', function ($q2) use ($request) {
                            $q2->where('learner_id', $request->user()->id);
                        });
                });
            } elseif (!$user || !$user->isAdmin()) {
                $query->where('id', null);
            }

            $results = $query->orderBy('order_index', 'asc')->paginate(30);

            if ($user && in_array($user->role, ['instructor', 'admin', 'super_admin'])) {
                $results->getCollection()->transform(function ($question) {
                    return $question->makeVisible('correct_answer');
                });
            }

            return response()->json([
                'status' => 200,
                'data'   => $results,
            ], 200);
        } catch (\Exception $e) {
            Log::error('ExamQuestionController@index: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving exam questions.',
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $user = $request->scholarUser();

        if (!$user || $user->role === 'student') {
            return response()->json([
                'status'  => 403,
                'message' => 'Forbidden.',
            ], 403);
        }

        $validator = Validations::validateExamQuestion($request->all());

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => $validator->messages(),
            ], 422);
        }

        $data = $request->all();
        $exam = Exam::find($data['exam_id']);

        if (!$this->canManageCourse($request, $exam?->course)) {
            return response()->json([
                'status'  => 403,
                'message' => 'Forbidden.',
            ], 403);
        }

        try {
            $examQuestion = ExamQuestion::create($data);
            $examQuestion->refresh();

            $this->syncExamApprovalAfterQuestionChange($user, $exam);

            return response()->json([
                'status'  => 201,
                'message' => 'Exam question created successfully.',
                'data'    => $examQuestion,
            ], 201);
        } catch (\Exception $e) {
            Log::error('ExamQuestionController@store: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while creating the exam question.',
            ], 500);
        }
    }

    public function show(Request $request, string $id)
    {
        try {
            $user = $request->scholarUser();
            $examQuestion = ExamQuestion::find($id);

            if (!$examQuestion) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Exam question not found.',
                ], 404);
            }

            if ($user && in_array($user->role, ['instructor', 'admin', 'super_admin'])) {
                $examQuestion->makeVisible('correct_answer');
            }

            return response()->json([
                'status' => 200,
                'data'   => $examQuestion,
            ], 200);
        } catch (\Exception $e) {
            Log::error('ExamQuestionController@show: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving the exam question.',
            ], 500);
        }
    }

    public function update(Request $request, string $id)
    {
        $user = $request->scholarUser();

        if (!$user || $user->role === 'student') {
            return response()->json([
                'status'  => 403,
                'message' => 'Forbidden.',
            ], 403);
        }

        $validator = Validations::validateExamQuestion($request->all());

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => $validator->messages(),
            ], 422);
        }

        try {
            $examQuestion = ExamQuestion::find($id);

            if (!$examQuestion) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Exam question not found.',
                ], 404);
            }

            if (!$this->canManageCourse($request, $examQuestion->exam?->course)) {
                return response()->json([
                    'status'  => 403,
                    'message' => 'Forbidden.',
                ], 403);
            }

            $data = $request->all();

            if (!$user->isAdmin()) {
                unset($data['exam_id']);
            }

            $examQuestion->update($data);

            $this->syncExamApprovalAfterQuestionChange($user, $examQuestion->exam);

            return response()->json([
                'status'  => 200,
                'message' => 'Exam question updated successfully.',
                'data'    => $examQuestion,
            ], 200);
        } catch (\Exception $e) {
            Log::error('ExamQuestionController@update: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while updating the exam question.',
            ], 500);
        }
    }

    public function delete(Request $request, string $id)
    {
        $user = $request->scholarUser();

        if (!$user || $user->role === 'student') {
            return response()->json([
                'status'  => 403,
                'message' => 'Forbidden.',
            ], 403);
        }

        try {
            $examQuestion = ExamQuestion::find($id);

            if (!$examQuestion) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Exam question not found.',
                ], 404);
            }

            $exam = $examQuestion->exam;

            if (!$this->canManageCourse($request, $exam?->course)) {
                return response()->json([
                    'status'  => 403,
                    'message' => 'Forbidden.',
                ], 403);
            }

            $examQuestion->delete();

            $this->syncExamApprovalAfterQuestionChange($user, $exam);

            return response()->json([
                'status'  => 200,
                'message' => 'Exam question deleted successfully.',
            ], 200);
        } catch (\Exception $e) {
            Log::error('ExamQuestionController@delete: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while deleting the exam question.',
            ], 500);
        }
    }

    /**
     * An instructor changing an exam's questions sends the parent exam back
     * for re-review; an admin's own change on a non-approved exam implicitly
     * re-approves it, since an admin editing their own review shouldn't
     * require a separate approval click.
     */
    private function syncExamApprovalAfterQuestionChange(ScholarUser $user, ?Exam $exam): void
    {
        if (!$exam) {
            return;
        }

        if ($user->isSuperAdmin()) {
            if ($exam->admin_approval_status !== 'approved') {
                $exam->forceFill(['admin_approval_status' => 'approved', 'admin_rejection_reason' => null])->save();
            }
            return;
        }

        $exam->forceFill(['admin_approval_status' => 'pending', 'admin_rejection_reason' => null])->save();

        NotificationService::notifySuperAdmins(
            'exam_review',
            "{$user->email} changed a question on the exam \"{$exam->title}\", which needs re-review."
        );
    }
}

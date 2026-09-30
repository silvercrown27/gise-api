<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Helpers\Validations;
use App\Models\AdminAuditLog;
use App\Models\Course;
use App\Models\Exam;
use App\Models\ScholarUser;
use App\Services\NotificationService;
use App\Traits\AuthorizesCourseOwnership;

class ExamController extends Controller
{
    use AuthorizesCourseOwnership;

    public function index(Request $request)
    {
        try {
            $user = ScholarUser::find($request->user()->id);

            $query = Exam::withCount('questions');

            if ($user && $user->role === 'instructor') {
                $query->whereHas('course', function ($q) use ($request) {
                    $q->manageableBy($request->user()->id);
                });
            } elseif ($user && $user->role === 'student') {
                // Students only ever see exams that are live (approved) on a
                // course they're actually enrolled in - never someone else's
                // pending/rejected drafts.
                $query->where('admin_approval_status', 'approved')
                    ->whereHas('course.enrollments', function ($q) use ($request) {
                        $q->where('learner_id', $request->user()->id);
                    });
            } elseif (!$user || !$user->isAdmin()) {
                $query->where('id', null);
            }

            if ($q = trim($request->input('q', ''))) {
                $query->where('title', 'like', '%' . $q . '%');
            }

            $results = $query->orderBy('created_at', 'desc')->paginate(min(max((int) $request->input('per_page', 10), 1), 100));

            return response()->json([
                'status' => 200,
                'data'   => $results,
            ], 200);
        } catch (\Exception $e) {
            Log::error('ExamController@index: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving exams.',
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $user = ScholarUser::find($request->user()->id);

        if (!$user || $user->role === 'student') {
            return response()->json([
                'status'  => 403,
                'message' => 'Forbidden.',
            ], 403);
        }

        $validator = Validations::validateExam($request->all());

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => $validator->messages(),
            ], 422);
        }

        $course = Course::find($request->input('course_id'));

        if (!$this->canManageCourse($request, $course)) {
            return response()->json([
                'status'  => 403,
                'message' => 'Forbidden.',
            ], 403);
        }

        try {
            $data = $request->all();

            // Super admins publish directly; everyone else's new content waits for them.
            $data['admin_approval_status'] = $user->isSuperAdmin() ? 'approved' : 'pending';
            $data['admin_rejection_reason'] = null;

            $exam = Exam::create($data);
            $exam->refresh();

            if (!$user->isSuperAdmin()) {
                NotificationService::notifySuperAdmins(
                    'exam_review',
                    "{$user->email} submitted a new exam \"{$exam->title}\" for review."
                );
            }

            return response()->json([
                'status'  => 201,
                'message' => 'Exam created successfully.',
                'data'    => $exam,
            ], 201);
        } catch (\Exception $e) {
            Log::error('ExamController@store: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while creating the exam.',
            ], 500);
        }
    }

    public function show(Request $request, string $id)
    {
        try {
            $user = ScholarUser::find($request->user()->id);
            $exam = Exam::with(['course.instructor', 'questions' => function ($q) {
                $q->orderBy('order_index', 'asc');
            }])->withCount('questions')->find($id);

            if (!$exam) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Exam not found.',
                ], 404);
            }

            $isAdmin = $user && $user->isAdmin();
            $isOwningInstructor = $user && $user->role === 'instructor'
                && $exam->course
                && $exam->course->isManageableBy($request->user()->id);

            if (!$isAdmin && !$isOwningInstructor && (!$user || $user->role !== 'student')) {
                return response()->json([
                    'status'  => 403,
                    'message' => 'Forbidden.',
                ], 403);
            }

            if ($isAdmin || $isOwningInstructor) {
                $exam->questions->each->makeVisible('correct_answer');
            } else {
                $exam->questions->each->makeHidden('correct_answer');
            }

            return response()->json([
                'status' => 200,
                'data'   => $exam,
            ], 200);
        } catch (\Exception $e) {
            Log::error('ExamController@show: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving the exam.',
            ], 500);
        }
    }

    public function update(Request $request, string $id)
    {
        try {
            $exam = Exam::find($id);

            if (!$exam) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Exam not found.',
                ], 404);
            }

            $user = ScholarUser::find($request->user()->id);

            $isAdmin = $user && $user->isAdmin();
            $isOwningInstructor = $user && $user->role === 'instructor'
                && $exam->course
                && $exam->course->isManageableBy($request->user()->id);

            if (!$isAdmin && !$isOwningInstructor) {
                return response()->json([
                    'status'  => 403,
                    'message' => 'Forbidden.',
                ], 403);
            }

            $validator = Validations::validateExam($request->all());

            if ($validator->fails()) {
                return response()->json([
                    'status'  => 422,
                    'message' => 'Validation failed.',
                    'errors'  => $validator->messages(),
                ], 422);
            }

            $data = $request->all();

            if (!$user->isAdmin()) {
                unset($data['course_id']);
            }

            // Only a super admin's edit stays live; anyone else's (admin or
            // instructor) sends the exam back for super admin review.
            $data['admin_approval_status'] = $user->isSuperAdmin() ? 'approved' : 'pending';
            $data['admin_rejection_reason'] = null;

            $exam->update($data);

            if (!$user->isSuperAdmin()) {
                NotificationService::notifySuperAdmins(
                    'exam_review',
                    "{$user->email} updated the exam \"{$exam->title}\", which needs re-review."
                );
            }

            return response()->json([
                'status'  => 200,
                'message' => 'Exam updated successfully.',
                'data'    => $exam,
            ], 200);
        } catch (\Exception $e) {
            Log::error('ExamController@update: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while updating the exam.',
            ], 500);
        }
    }

    public function delete(Request $request, string $id)
    {
        try {
            $exam = Exam::find($id);

            if (!$exam) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Exam not found.',
                ], 404);
            }

            $user = ScholarUser::find($request->user()->id);

            $isAdmin = $user && $user->isAdmin();
            $isOwningInstructor = $user && $user->role === 'instructor'
                && $exam->course
                && $exam->course->isManageableBy($request->user()->id);

            if (!$isAdmin && !$isOwningInstructor) {
                return response()->json([
                    'status'  => 403,
                    'message' => 'Forbidden.',
                ], 403);
            }

            $exam->delete();

            return response()->json([
                'status'  => 200,
                'message' => 'Exam deleted successfully.',
            ], 200);
        } catch (\Exception $e) {
            Log::error('ExamController@delete: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while deleting the exam.',
            ], 500);
        }
    }

    public function forReview(Request $request)
    {
        $user = ScholarUser::find($request->user()->id);

        if (!$user || !$user->isAdmin()) {
            return response()->json([
                'status'  => 403,
                'message' => 'Forbidden.',
            ], 403);
        }

        try {
            $query = Exam::with(['course.instructor'])
                ->withCount('questions');

            if ($q = trim($request->input('q', ''))) {
                $query->where('title', 'like', '%' . $q . '%');
            }

            if ($status = trim($request->input('admin_approval_status', ''))) {
                $query->where('admin_approval_status', $status);
            }

            $results = $query->orderBy('created_at', 'desc')->paginate(10);

            return response()->json([
                'status' => 200,
                'data'   => $results,
            ], 200);
        } catch (\Exception $e) {
            Log::error('ExamController@forReview: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving exams for review.',
            ], 500);
        }
    }

    public function setApprovalStatus(Request $request, string $id)
    {
        $user = ScholarUser::find($request->user()->id);

        // Approvals are a super admin decision.
        if (!$user || !$user->isSuperAdmin()) {
            return response()->json([
                'status'  => 403,
                'message' => 'Forbidden.',
            ], 403);
        }

        $status = $request->input('admin_approval_status');

        if (!in_array($status, ['pending', 'approved', 'rejected'], true)) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => ['admin_approval_status' => ['Must be one of: pending, approved, rejected.']],
            ], 422);
        }

        try {
            $exam = Exam::find($id);

            if (!$exam) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Exam not found.',
                ], 404);
            }

            $exam->forceFill([
                'admin_approval_status' => $status,
                'admin_rejection_reason' => $status === 'rejected' ? $request->input('admin_rejection_reason') : null,
            ])->save();

            $actionByStatus = [
                'approved' => 'approve_exam',
                'rejected' => 'reject_exam',
                'pending' => 'reset_exam_approval',
            ];

            AdminAuditLog::create([
                'admin_id' => $request->user()->id,
                'action' => $actionByStatus[$status],
                'target_type' => 'exam',
                'target_id' => $exam->id,
                'notes' => $status === 'rejected' ? $exam->admin_rejection_reason : null,
            ]);

            $link = '/admin/content/exams/' . $exam->id;
            if ($status === 'approved') {
                NotificationService::notifyReviewOutcome('exam_review', "The exam \"{$exam->title}\" was approved and is now live.", $link, $request->user()->id);
            } elseif ($status === 'rejected') {
                NotificationService::notifyReviewOutcome('exam_review', "The exam \"{$exam->title}\" was rejected." . ($exam->admin_rejection_reason ? " Reason: {$exam->admin_rejection_reason}" : ''), $link, $request->user()->id);
            }

            return response()->json([
                'status'  => 200,
                'message' => 'Exam approval status updated successfully.',
                'data'    => $exam,
            ], 200);
        } catch (\Exception $e) {
            Log::error('ExamController@setApprovalStatus: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while updating the approval status.',
            ], 500);
        }
    }
}

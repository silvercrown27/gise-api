<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Helpers\Validations;
use App\Models\AdminAuditLog;
use App\Models\CourseModule;
use App\Models\ModuleQuiz;
use App\Models\ScholarUser;
use App\Services\NotificationService;
use App\Traits\AuthorizesCourseOwnership;

class ModuleQuizController extends Controller
{
    use AuthorizesCourseOwnership;

    public function index(Request $request)
    {
        try {
            $query = ModuleQuiz::withCount('questions');

            if ($moduleId = trim($request->input('module_id', ''))) {
                $query->where('module_id', $moduleId);
            }

            $results = $query->orderBy('created_at', 'desc')->paginate(10);

            return response()->json([
                'status' => 200,
                'data'   => $results,
            ], 200);
        } catch (\Exception $e) {
            Log::error('ModuleQuizController@index: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving module quizzes.',
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $user = ScholarUser::find($request->user()->id);

        if (!$user || !in_array($user->role, ['instructor', 'admin'])) {
            return response()->json([
                'status'  => 403,
                'message' => 'Forbidden.',
            ], 403);
        }

        $validator = Validations::validateModuleQuiz($request->all());

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => $validator->messages(),
            ], 422);
        }

        $module = CourseModule::find($request->input('module_id'));

        if (!$this->canManageCourse($request, $module?->course)) {
            return response()->json([
                'status'  => 403,
                'message' => 'Forbidden.',
            ], 403);
        }

        try {
            $data = $request->all();

            if ($user->role === 'admin') {
                $data['admin_approval_status'] = 'approved';
                $data['admin_rejection_reason'] = null;
            } else {
                $data['admin_approval_status'] = 'pending';
                $data['admin_rejection_reason'] = null;
            }

            $quiz = ModuleQuiz::create($data);
            $quiz->refresh();

            if ($user->role !== 'admin') {
                NotificationService::notifyAdmins(
                    'quiz_review',
                    "{$user->email} submitted a new quiz \"{$quiz->title}\" for review."
                );
            }

            return response()->json([
                'status'  => 201,
                'message' => 'Module quiz created successfully.',
                'data'    => $quiz,
            ], 201);
        } catch (\Exception $e) {
            Log::error('ModuleQuizController@store: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while creating the module quiz.',
            ], 500);
        }
    }

    public function show(Request $request, string $id)
    {
        try {
            $quiz = ModuleQuiz::with([
                'questions' => function ($q) {
                    $q->orderBy('order_index', 'asc');
                },
                'module.course.instructor',
            ])->find($id);

            if (!$quiz) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Module quiz not found.',
                ], 404);
            }

            // This route has no auth:sanctum middleware (it's intentionally public
            // so students can fetch the quiz to take), so $request->user() is never
            // populated even with a valid Bearer token - resolve the sanctum guard
            // directly to still reveal correct_option_key to an authenticated
            // instructor/admin reviewing the quiz.
            $authUser = $request->user('sanctum');
            $caller = $authUser ? ScholarUser::find($authUser->id) : null;

            if ($caller && in_array($caller->role, ['instructor', 'admin'])) {
                $quiz->questions->each->makeVisible('correct_option_key');
            } else {
                $quiz->questions->each->makeHidden('correct_option_key');
            }

            return response()->json([
                'status' => 200,
                'data'   => $quiz,
            ], 200);
        } catch (\Exception $e) {
            Log::error('ModuleQuizController@show: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving the module quiz.',
            ], 500);
        }
    }

    public function update(Request $request, string $id)
    {
        $user = ScholarUser::find($request->user()->id);

        if (!$user || !in_array($user->role, ['instructor', 'admin'])) {
            return response()->json([
                'status'  => 403,
                'message' => 'Forbidden.',
            ], 403);
        }

        $validator = Validations::validateModuleQuiz($request->all());

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => $validator->messages(),
            ], 422);
        }

        try {
            $quiz = ModuleQuiz::find($id);

            if (!$quiz) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Module quiz not found.',
                ], 404);
            }

            $user = ScholarUser::find($request->user()->id);

            if (!$this->canManageCourse($request, $quiz->module?->course)) {
                return response()->json([
                    'status'  => 403,
                    'message' => 'Forbidden.',
                ], 403);
            }

            $data = $request->all();

            if ($user->role === 'admin') {
                $data['admin_approval_status'] = 'approved';
                $data['admin_rejection_reason'] = null;
            } else {
                unset($data['module_id']);
                // An instructor's own edit always sends the quiz back for
                // re-review, even if it was previously approved.
                $data['admin_approval_status'] = 'pending';
                $data['admin_rejection_reason'] = null;
            }

            $quiz->update($data);

            if ($user->role !== 'admin') {
                NotificationService::notifyAdmins(
                    'quiz_review',
                    "{$user->email} updated the quiz \"{$quiz->title}\", which needs re-review."
                );
            }

            return response()->json([
                'status'  => 200,
                'message' => 'Module quiz updated successfully.',
                'data'    => $quiz,
            ], 200);
        } catch (\Exception $e) {
            Log::error('ModuleQuizController@update: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while updating the module quiz.',
            ], 500);
        }
    }

    public function delete(Request $request, string $id)
    {
        $user = ScholarUser::find($request->user()->id);

        if (!$user || !in_array($user->role, ['instructor', 'admin'])) {
            return response()->json([
                'status'  => 403,
                'message' => 'Forbidden.',
            ], 403);
        }

        try {
            $quiz = ModuleQuiz::find($id);

            if (!$quiz) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Module quiz not found.',
                ], 404);
            }

            if (!$this->canManageCourse($request, $quiz->module?->course)) {
                return response()->json([
                    'status'  => 403,
                    'message' => 'Forbidden.',
                ], 403);
            }

            $quiz->delete();

            return response()->json([
                'status'  => 200,
                'message' => 'Module quiz deleted successfully.',
            ], 200);
        } catch (\Exception $e) {
            Log::error('ModuleQuizController@delete: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while deleting the module quiz.',
            ], 500);
        }
    }

    public function forReview(Request $request)
    {
        $user = ScholarUser::find($request->user()->id);

        if (!$user || $user->role !== 'admin') {
            return response()->json([
                'status'  => 403,
                'message' => 'Forbidden.',
            ], 403);
        }

        try {
            $query = ModuleQuiz::with(['module.course.instructor'])
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
            Log::error('ModuleQuizController@forReview: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving quizzes for review.',
            ], 500);
        }
    }

    public function setApprovalStatus(Request $request, string $id)
    {
        $user = ScholarUser::find($request->user()->id);

        if (!$user || $user->role !== 'admin') {
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
            $quiz = ModuleQuiz::find($id);

            if (!$quiz) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Module quiz not found.',
                ], 404);
            }

            $quiz->forceFill([
                'admin_approval_status' => $status,
                'admin_rejection_reason' => $status === 'rejected' ? $request->input('admin_rejection_reason') : null,
            ])->save();

            $actionByStatus = [
                'approved' => 'approve_quiz',
                'rejected' => 'reject_quiz',
                'pending' => 'reset_quiz_approval',
            ];

            AdminAuditLog::create([
                'admin_id' => $request->user()->id,
                'action' => $actionByStatus[$status],
                'target_type' => 'module_quiz',
                'target_id' => $quiz->id,
                'notes' => $status === 'rejected' ? $quiz->admin_rejection_reason : null,
            ]);

            $recipientIds = $quiz->module?->course?->reviewRecipientIds($request->user()->id) ?? [];

            foreach ($recipientIds as $recipientId) {
                if ($status === 'approved') {
                    NotificationService::notifyUser(
                        $recipientId,
                        'quiz_review',
                        "Your quiz \"{$quiz->title}\" has been approved and is live again."
                    );
                } elseif ($status === 'rejected') {
                    NotificationService::notifyUser(
                        $recipientId,
                        'quiz_review',
                        "Your quiz \"{$quiz->title}\" was rejected." . ($quiz->admin_rejection_reason ? " Reason: {$quiz->admin_rejection_reason}" : '')
                    );
                }
            }

            return response()->json([
                'status'  => 200,
                'message' => 'Module quiz approval status updated successfully.',
                'data'    => $quiz,
            ], 200);
        } catch (\Exception $e) {
            Log::error('ModuleQuizController@setApprovalStatus: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while updating the approval status.',
            ], 500);
        }
    }
}

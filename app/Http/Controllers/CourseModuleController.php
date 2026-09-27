<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Helpers\Validations;
use App\Models\AdminAuditLog;
use App\Models\Course;
use App\Models\CourseModule;
use App\Models\ScholarUser;
use App\Services\NotificationService;
use App\Traits\AuthorizesCourseOwnership;

class CourseModuleController extends Controller
{
    use AuthorizesCourseOwnership;

    public function index(Request $request)
    {
        try {
            $query = CourseModule::withCount('lessons')
                ->with(['quiz' => function ($quiz) {
                    $quiz->withCount('questions');
                }]);

            if ($request->boolean('with_lessons')) {
                $query->with(['lessons' => function ($lessons) {
                    $lessons->select(['id', 'module_id', 'title', 'duration_minutes', 'order_index'])
                        ->orderBy('order_index', 'asc');
                }]);
            }

            if ($q = trim($request->input('q', ''))) {
                $query->where('title', 'like', '%' . $q . '%');
            }

            if ($courseId = trim($request->input('course_id', ''))) {
                $query->where('course_id', $courseId);
            }

            $results = $query->orderBy('order_index', 'asc')->paginate(10);

            return response()->json([
                'status' => 200,
                'data'   => $results,
            ], 200);
        } catch (\Exception $e) {
            Log::error('CourseModuleController@index: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving course modules.',
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

        $validator = Validations::validateCourseModule($request->all());

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

            if ($user->role === 'admin') {
                $data['admin_approval_status'] = 'approved';
                $data['admin_rejection_reason'] = null;
            } else {
                $data['admin_approval_status'] = 'pending';
                $data['admin_rejection_reason'] = null;
            }

            $courseModule = CourseModule::create($data);
            $courseModule->refresh();

            if ($user->role !== 'admin') {
                NotificationService::notifyAdmins(
                    'module_review',
                    "{$user->email} added a new module \"{$courseModule->title}\" for review."
                );
            }

            return response()->json([
                'status'  => 201,
                'message' => 'Course module created successfully.',
                'data'    => $courseModule,
            ], 201);
        } catch (\Exception $e) {
            Log::error('CourseModuleController@store: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while creating the course module.',
            ], 500);
        }
    }

    public function show(Request $request, string $id)
    {
        try {
            $courseModule = CourseModule::withCount('lessons')->find($id);

            if (!$courseModule) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Course module not found.',
                ], 404);
            }

            // This route has no auth:sanctum middleware (it's intentionally public,
            // matching course-lessons/module-quizzes), so $request->user() is never
            // populated even with a valid Bearer token - resolve the sanctum guard
            // directly to still reveal the course/instructor and full lesson list to
            // an authenticated admin or the owning instructor reviewing the module.
            $authUser = $request->user('sanctum');
            $caller = $authUser ? ScholarUser::find($authUser->id) : null;
            $course = Course::find($courseModule->course_id);
            $canManage = $caller && ($caller->role === 'admin'
                || ($caller->role === 'instructor' && $course?->isManageableBy($authUser->id)));

            if ($canManage) {
                $courseModule->load(['course.instructor', 'lessons' => function ($lessons) {
                    $lessons->orderBy('order_index', 'asc');
                }]);
            }

            return response()->json([
                'status' => 200,
                'data'   => $courseModule,
            ], 200);
        } catch (\Exception $e) {
            Log::error('CourseModuleController@show: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving the course module.',
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

        $validator = Validations::validateCourseModule($request->all());

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => $validator->messages(),
            ], 422);
        }

        try {
            $courseModule = CourseModule::find($id);

            if (!$courseModule) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Course module not found.',
                ], 404);
            }

            $user = ScholarUser::find($request->user()->id);

            if (!$this->canManageCourse($request, $courseModule->course)) {
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
                unset($data['course_id']);
                // An instructor's own edit always sends the module back for
                // re-review, even if it was previously approved.
                $data['admin_approval_status'] = 'pending';
                $data['admin_rejection_reason'] = null;
            }

            $courseModule->update($data);

            if ($user->role !== 'admin') {
                NotificationService::notifyAdmins(
                    'module_review',
                    "{$user->email} updated the module \"{$courseModule->title}\", which needs re-review."
                );
            }

            return response()->json([
                'status'  => 200,
                'message' => 'Course module updated successfully.',
                'data'    => $courseModule,
            ], 200);
        } catch (\Exception $e) {
            Log::error('CourseModuleController@update: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while updating the course module.',
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
            $courseModule = CourseModule::find($id);

            if (!$courseModule) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Course module not found.',
                ], 404);
            }

            if (!$this->canManageCourse($request, $courseModule->course)) {
                return response()->json([
                    'status'  => 403,
                    'message' => 'Forbidden.',
                ], 403);
            }

            $courseModule->delete();

            return response()->json([
                'status'  => 200,
                'message' => 'Course module deleted successfully.',
            ], 200);
        } catch (\Exception $e) {
            Log::error('CourseModuleController@delete: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while deleting the course module.',
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
            $query = CourseModule::with(['course.instructor'])
                ->withCount('lessons');

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
            Log::error('CourseModuleController@forReview: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving modules for review.',
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
            $courseModule = CourseModule::find($id);

            if (!$courseModule) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Course module not found.',
                ], 404);
            }

            $courseModule->forceFill([
                'admin_approval_status' => $status,
                'admin_rejection_reason' => $status === 'rejected' ? $request->input('admin_rejection_reason') : null,
            ])->save();

            $actionByStatus = [
                'approved' => 'approve_module',
                'rejected' => 'reject_module',
                'pending' => 'reset_module_approval',
            ];

            AdminAuditLog::create([
                'admin_id' => $request->user()->id,
                'action' => $actionByStatus[$status],
                'target_type' => 'course_module',
                'target_id' => $courseModule->id,
                'notes' => $status === 'rejected' ? $courseModule->admin_rejection_reason : null,
            ]);

            $recipientIds = $courseModule->course?->reviewRecipientIds($request->user()->id) ?? [];

            foreach ($recipientIds as $recipientId) {
                if ($status === 'approved') {
                    NotificationService::notifyUser(
                        $recipientId,
                        'module_review',
                        "Your module \"{$courseModule->title}\" has been approved and is now live."
                    );
                } elseif ($status === 'rejected') {
                    NotificationService::notifyUser(
                        $recipientId,
                        'module_review',
                        "Your module \"{$courseModule->title}\" was rejected." . ($courseModule->admin_rejection_reason ? " Reason: {$courseModule->admin_rejection_reason}" : '')
                    );
                }
            }

            return response()->json([
                'status'  => 200,
                'message' => 'Module approval status updated successfully.',
                'data'    => $courseModule,
            ], 200);
        } catch (\Exception $e) {
            Log::error('CourseModuleController@setApprovalStatus: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while updating the approval status.',
            ], 500);
        }
    }
}

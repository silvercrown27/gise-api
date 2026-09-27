<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\Controller;
use App\Helpers\Utilities;
use App\Helpers\Validations;
use App\Models\CourseLesson;
use App\Models\CourseModule;
use App\Models\ScholarUser;
use App\Services\NotificationService;
use App\Traits\AuthorizesCourseOwnership;

class CourseLessonController extends Controller
{
    use AuthorizesCourseOwnership;

    public function index(Request $request)
    {
        try {
            $query = CourseLesson::withCount('resources');

            if ($q = trim($request->input('q', ''))) {
                $query->where('title', 'like', '%' . $q . '%');
            }

            if ($moduleId = trim($request->input('module_id', ''))) {
                $query->where('module_id', $moduleId);
            }

            $results = $query->orderBy('order_index', 'asc')->paginate(10);

            return response()->json([
                'status' => 200,
                'data'   => $results,
            ], 200);
        } catch (\Exception $e) {
            Log::error('CourseLessonController@index: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving course lessons.',
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $user = ScholarUser::find($request->user()->id);

        if (!$user || !in_array($user->role, ['instructor', 'admin', 'super_admin'])) {
            return response()->json([
                'status'  => 403,
                'message' => 'Forbidden.',
            ], 403);
        }

        $data = $request->all();

        if ($request->hasFile('content_file')) {
            $file = $request->file('content_file');
            $upload = Utilities::uploadFile($file, 'course-lessons/' . ($data['module_id'] ?? 'general'));

            if ($upload['status'] !== 200) {
                return response()->json([
                    'status'  => 500,
                    'message' => $upload['message'],
                ], 500);
            }

            $data['content_url_or_body'] = Storage::url($upload['path']);
        }

        $validator = Validations::validateCourseLesson($data);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => $validator->messages(),
            ], 422);
        }

        $module = CourseModule::find($data['module_id']);

        if (!$this->canManageCourse($request, $module?->course)) {
            return response()->json([
                'status'  => 403,
                'message' => 'Forbidden.',
            ], 403);
        }

        try {
            $courseLesson = CourseLesson::create($data);
            $courseLesson->refresh();

            $this->syncModuleApprovalAfterLessonChange($user, $module);

            return response()->json([
                'status'  => 201,
                'message' => 'Course lesson created successfully.',
                'data'    => $courseLesson,
            ], 201);
        } catch (\Exception $e) {
            Log::error('CourseLessonController@store: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while creating the course lesson.',
            ], 500);
        }
    }

    public function show(string $id)
    {
        try {
            $courseLesson = CourseLesson::withCount('resources')->find($id);

            if (!$courseLesson) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Course lesson not found.',
                ], 404);
            }

            return response()->json([
                'status' => 200,
                'data'   => $courseLesson,
            ], 200);
        } catch (\Exception $e) {
            Log::error('CourseLessonController@show: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving the course lesson.',
            ], 500);
        }
    }

    public function update(Request $request, string $id)
    {
        $user = ScholarUser::find($request->user()->id);

        if (!$user || !in_array($user->role, ['instructor', 'admin', 'super_admin'])) {
            return response()->json([
                'status'  => 403,
                'message' => 'Forbidden.',
            ], 403);
        }

        $data = $request->all();

        if ($request->hasFile('content_file')) {
            $file = $request->file('content_file');
            $upload = Utilities::uploadFile($file, 'course-lessons/' . ($data['module_id'] ?? 'general'));

            if ($upload['status'] !== 200) {
                return response()->json([
                    'status'  => 500,
                    'message' => $upload['message'],
                ], 500);
            }

            $data['content_url_or_body'] = Storage::url($upload['path']);
        }

        $validator = Validations::validateCourseLesson($data);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => $validator->messages(),
            ], 422);
        }

        try {
            $courseLesson = CourseLesson::find($id);

            if (!$courseLesson) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Course lesson not found.',
                ], 404);
            }

            $module = $courseLesson->module;

            if (!$this->canManageCourse($request, $module?->course)) {
                return response()->json([
                    'status'  => 403,
                    'message' => 'Forbidden.',
                ], 403);
            }

            if (!$user->isAdmin()) {
                unset($data['module_id']);
            }

            $courseLesson->update($data);

            $this->syncModuleApprovalAfterLessonChange($user, $module);

            return response()->json([
                'status'  => 200,
                'message' => 'Course lesson updated successfully.',
                'data'    => $courseLesson,
            ], 200);
        } catch (\Exception $e) {
            Log::error('CourseLessonController@update: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while updating the course lesson.',
            ], 500);
        }
    }

    public function delete(Request $request, string $id)
    {
        $user = ScholarUser::find($request->user()->id);

        if (!$user || !in_array($user->role, ['instructor', 'admin', 'super_admin'])) {
            return response()->json([
                'status'  => 403,
                'message' => 'Forbidden.',
            ], 403);
        }

        try {
            $courseLesson = CourseLesson::find($id);

            if (!$courseLesson) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Course lesson not found.',
                ], 404);
            }

            $module = $courseLesson->module;

            if (!$this->canManageCourse($request, $module?->course)) {
                return response()->json([
                    'status'  => 403,
                    'message' => 'Forbidden.',
                ], 403);
            }

            $courseLesson->delete();

            $this->syncModuleApprovalAfterLessonChange($user, $module);

            return response()->json([
                'status'  => 200,
                'message' => 'Course lesson deleted successfully.',
            ], 200);
        } catch (\Exception $e) {
            Log::error('CourseLessonController@delete: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while deleting the course lesson.',
            ], 500);
        }
    }

    /**
     * An instructor changing a module's lessons sends the parent module back
     * for re-review; an admin's own change on a non-approved module implicitly
     * re-approves it, since an admin editing their own review shouldn't
     * require a separate approval click.
     */
    private function syncModuleApprovalAfterLessonChange(ScholarUser $user, ?CourseModule $module): void
    {
        if (!$module) {
            return;
        }

        if ($user->isSuperAdmin()) {
            if ($module->admin_approval_status !== 'approved') {
                $module->forceFill(['admin_approval_status' => 'approved', 'admin_rejection_reason' => null])->save();
            }
            return;
        }

        $module->forceFill(['admin_approval_status' => 'pending', 'admin_rejection_reason' => null])->save();

        NotificationService::notifySuperAdmins(
            'module_review',
            "{$user->email} changed a lesson in the module \"{$module->title}\", which needs re-review."
        );
    }
}

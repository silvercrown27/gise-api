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
use App\Models\AdminAuditLog;
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

            // Learners (and anonymous visitors) only ever see approved lessons.
            if (!$this->canSeeUnapproved($request)) {
                $query->approved();
            }

            $perPage = min(max((int) $request->input('per_page', 10), 1), 100);
            $results = $query->orderBy('order_index', 'asc')->paginate($perPage);

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
        $user = $request->scholarUser();

        if (!$user || !in_array($user->role, ['instructor', 'admin', 'super_admin'])) {
            return response()->json([
                'status'  => 403,
                'message' => 'Forbidden.',
            ], 403);
        }

        $data = $request->all();

        $validator = Validations::validateCourseLesson($data);

        if ($validator->fails() || ($fileError = $this->contentFileError($request))) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => $validator->fails() ? $validator->messages() : ['content_file' => [$fileError]],
            ], 422);
        }

        $module = CourseModule::find($data['module_id']);

        if (!$this->canManageCourse($request, $module?->course)) {
            return response()->json([
                'status'  => 403,
                'message' => 'Forbidden.',
            ], 403);
        }

        // Only now - validated and authorised - is the upload written to disk.
        if ($failed = $this->storeContentFile($request, $data)) {
            return $failed;
        }

        try {
            $courseLesson = CourseLesson::create($data);
            $this->markReviewStatus($user, $courseLesson);
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

    public function show(Request $request, string $id)
    {
        try {
            $courseLesson = CourseLesson::withCount('resources')->find($id);

            if ($courseLesson && $courseLesson->admin_approval_status !== 'approved' && !$this->canSeeUnapproved($request)) {
                $courseLesson = null;
            }

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
        $user = $request->scholarUser();

        if (!$user || !in_array($user->role, ['instructor', 'admin', 'super_admin'])) {
            return response()->json([
                'status'  => 403,
                'message' => 'Forbidden.',
            ], 403);
        }

        $data = $request->all();

        $validator = Validations::validateCourseLesson($data);

        if ($validator->fails() || ($fileError = $this->contentFileError($request))) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => $validator->fails() ? $validator->messages() : ['content_file' => [$fileError]],
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

            if ($failed = $this->storeContentFile($request, $data)) {
                return $failed;
            }

            $courseLesson->update($data);
            $this->markReviewStatus($user, $courseLesson);

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
        $user = $request->scholarUser();

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
     * Approve, reject or reset one lesson. Super admin only.
     */
    public function setApprovalStatus(Request $request, string $id)
    {
        $user = $request->scholarUser();

        if (!$user || !$user->isSuperAdmin()) {
            return response()->json(['status' => 403, 'message' => 'Forbidden.'], 403);
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
            $lesson = CourseLesson::find($id);

            if (!$lesson) {
                return response()->json(['status' => 404, 'message' => 'Course lesson not found.'], 404);
            }

            $lesson->forceFill([
                'admin_approval_status' => $status,
                'admin_rejection_reason' => $status === 'rejected' ? $request->input('admin_rejection_reason') : null,
            ])->save();

            AdminAuditLog::create([
                'admin_id' => $request->user()->id,
                'action' => ['approved' => 'approve_lesson', 'rejected' => 'reject_lesson', 'pending' => 'reset_lesson_approval'][$status],
                'target_type' => 'course_lesson',
                'target_id' => $lesson->id,
                'notes' => $status === 'rejected' ? $lesson->admin_rejection_reason : null,
            ]);

            if ($status === 'rejected') {
                $module = $lesson->module;
                NotificationService::notifyReviewOutcome(
                    'module_review',
                    "The lesson \"{$lesson->title}\" was rejected." . ($lesson->admin_rejection_reason ? " Reason: {$lesson->admin_rejection_reason}" : ''),
                    '/admin/content/lessons?course=' . $module?->course_id . '&module=' . $lesson->module_id,
                    $request->user()->id
                );
            }

            return response()->json([
                'status'  => 200,
                'message' => 'Lesson approval status updated successfully.',
                'data'    => $lesson,
            ], 200);
        } catch (\Exception $e) {
            Log::error('CourseLessonController@setApprovalStatus: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while updating the lesson approval status.',
            ], 500);
        }
    }

    /**
     * Approve every lesson in a module that isn't approved yet. Super admin only.
     */
    public function approveAllInModule(Request $request, string $moduleId)
    {
        $user = $request->scholarUser();

        if (!$user || !$user->isSuperAdmin()) {
            return response()->json(['status' => 403, 'message' => 'Forbidden.'], 403);
        }

        try {
            $module = CourseModule::find($moduleId);

            if (!$module) {
                return response()->json(['status' => 404, 'message' => 'Course module not found.'], 404);
            }

            $lessons = CourseLesson::where('module_id', $module->id)->where('admin_approval_status', '!=', 'approved')->get();

            foreach ($lessons as $lesson) {
                $lesson->forceFill(['admin_approval_status' => 'approved', 'admin_rejection_reason' => null])->save();
            }

            if ($lessons->isNotEmpty()) {
                AdminAuditLog::create([
                    'admin_id' => $request->user()->id,
                    'action' => 'approve_lesson',
                    'target_type' => 'course_module',
                    'target_id' => $module->id,
                    'notes' => "Approved all lessons ({$lessons->count()}) in \"{$module->title}\".",
                ]);
            }

            return response()->json([
                'status'  => 200,
                'message' => $lessons->count() === 1 ? '1 lesson approved.' : "{$lessons->count()} lessons approved.",
                'data'    => ['approved' => $lessons->count()],
            ], 200);
        } catch (\Exception $e) {
            Log::error('CourseLessonController@approveAllInModule: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while approving the lessons.',
            ], 500);
        }
    }

    /** Admins and instructors see lessons in every review state; everyone else, approved only. */
    private function canSeeUnapproved(Request $request): bool
    {
        // These routes are public, so resolve the token by hand (see ModuleQuizQuestionController@index).
        $authUser = $request->user('sanctum');
        $user = $authUser ? ScholarUser::find($authUser->id) : null;

        return $user && in_array($user->role, ['instructor', 'admin', 'super_admin'], true);
    }

    /** A super admin's own lesson is live at once; anyone else's waits for review. */
    private function markReviewStatus(ScholarUser $user, CourseLesson $lesson): void
    {
        $lesson->forceFill([
            'admin_approval_status' => $user->isSuperAdmin() ? 'approved' : 'pending',
            'admin_rejection_reason' => null,
        ])->save();
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

    /** A lesson's uploaded file may be any document or media, up to the 100 MB the API accepts. */
    private function contentFileError(Request $request): ?string
    {
        if (!$request->hasFile('content_file')) {
            return null;
        }

        $check = \Illuminate\Support\Facades\Validator::make(
            ['content_file' => $request->file('content_file')],
            ['content_file' => 'file|max:102400'],
            ['content_file.max' => 'The file must be 100 MB or smaller.', 'content_file.uploaded' => 'The upload failed - the file may be too large.']
        );

        return $check->fails() ? $check->errors()->first() : null;
    }

    /** Saves the uploaded lesson file and points the lesson's content at it. */
    private function storeContentFile(Request $request, array &$data): ?\Illuminate\Http\JsonResponse
    {
        if (!$request->hasFile('content_file')) {
            return null;
        }

        $upload = Utilities::uploadFile($request->file('content_file'), 'course-lessons/' . ($data['module_id'] ?? 'general'));

        if ($upload['status'] !== 200) {
            return response()->json(['status' => 500, 'message' => $upload['message']], 500);
        }

        $data['content_url_or_body'] = Storage::url($upload['path']);

        return null;
    }
}

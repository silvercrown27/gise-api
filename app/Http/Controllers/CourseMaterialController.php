<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Controller;
use App\Helpers\Utilities;
use App\Jobs\NotifyStaff;
use App\Models\AdminAuditLog;
use App\Models\Course;
use App\Models\CourseMaterial;
use App\Models\CourseModule;
use App\Models\Enrollment;
use App\Models\ScholarUser;
use App\Services\NotificationService;
use App\Traits\AuthorizesCourseOwnership;

/**
 * Course content PDF, brochure and per-module slides. A course's mentors
 * upload them, an admin approves them, and the latest approved file of each
 * kind is the live one (the approved brochure is what gets emailed).
 */
class CourseMaterialController extends Controller
{
    use AuthorizesCourseOwnership;

    private const DEFAULT_TITLES = [
        'course_content' => 'Course content',
        'brochure' => 'Course brochure',
        'module_slides' => 'Module slides',
    ];

    public function index(Request $request)
    {
        try {
            $user = $request->scholarUser();
            // Super admins see everything admins do.
            $role = $user?->isAdmin() ? 'admin' : ($user->role ?? 'student');

            $query = CourseMaterial::with(['course:id,title,code', 'module:id,title,order_index', 'uploader:id,name,email']);

            if ($role === 'instructor') {
                $query->whereHas('course', fn ($q) => $q->manageableBy($request->user()->id));
            } elseif ($role !== 'admin') {
                // Learners get the approved files of courses they're enrolled in.
                $query->where('status', 'approved')
                    ->whereIn('course_id', Enrollment::where('learner_id', $request->user()->id)->select('course_id'));
            }

            foreach (['course_id', 'module_id', 'type', 'status'] as $filter) {
                if ($value = trim($request->input($filter, ''))) {
                    $query->where($filter, $value);
                }
            }

            $perPage = min(max((int) $request->input('per_page', 25), 1), 100);

            return response()->json([
                'status' => 200,
                'data'   => $query->orderBy('created_at', 'desc')->paginate($perPage),
            ], 200);
        } catch (\Exception $e) {
            Log::error('CourseMaterialController@index: ' . $e->getMessage());
            return response()->json(['status' => 500, 'message' => 'An error occurred while retrieving course materials.'], 500);
        }
    }

    public function store(Request $request)
    {
        $course = Course::find($request->input('course_id'));

        if (!$this->canManageCourse($request, $course)) {
            return response()->json([
                'status'  => 403,
                'message' => 'Only an admin or an approved mentor of this course can upload its materials.',
            ], 403);
        }

        $type = $request->input('type');
        $extensions = CourseMaterial::EXTENSIONS[$type] ?? ['pdf'];

        $validator = Validator::make($request->all(), [
            'type'      => 'required|string|in:' . implode(',', CourseMaterial::TYPES),
            'module_id' => 'nullable|required_if:type,module_slides|uuid|exists:course_modules,id',
            'title'     => 'nullable|string|max:255',
            'file'      => [
                'required',
                'file',
                'max:' . CourseMaterial::MAX_UPLOAD_KB,
                'extensions:' . implode(',', $extensions),
                // .pptx is a zip container and legacy .ppt an OLE file, so
                // allow those detected types alongside the real ones.
                'mimetypes:' . implode(',', CourseMaterial::mimeTypesFor($extensions)),
            ],
        ], [
            'file.extensions'       => 'Upload a ' . strtoupper(implode(' or ', $extensions)) . ' file.',
            'file.mimetypes'        => 'Upload a ' . strtoupper(implode(' or ', $extensions)) . ' file.',
            'file.max'              => 'The file must be 100 MB or smaller.',
            'module_id.required_if' => 'Choose the module these slides belong to.',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 422, 'message' => 'Validation failed.', 'errors' => $validator->messages()], 422);
        }

        $module = $type === 'module_slides' ? CourseModule::find($request->input('module_id')) : null;

        if ($module && (string) $module->course_id !== (string) $course->id) {
            return response()->json([
                'status'  => 422,
                'message' => 'That module belongs to a different course.',
                'errors'  => ['module_id' => ['That module belongs to a different course.']],
            ], 422);
        }

        try {
            $file = $request->file('file');
            $upload = Utilities::uploadFile($file, "course-materials/{$course->id}");

            if ($upload['status'] !== 200) {
                return response()->json(['status' => 500, 'message' => $upload['message']], 500);
            }

            // A super admin's own upload needs no second pair of eyes.
            $isAdmin = $this->isSuperAdminRequest($request);

            $material = CourseMaterial::create([
                'course_id' => $course->id,
                'module_id' => $module?->id,
                'type' => $type,
                'title' => trim((string) $request->input('title')) ?: ($module ? "{$module->title} slides" : self::DEFAULT_TITLES[$type]),
                'file_url' => Storage::url($upload['path']),
                'file_type' => strtolower($file->getClientOriginalExtension()),
                'file_size' => $file->getSize(),
                'uploaded_by' => $request->user()->id,
                'status' => $isAdmin ? 'approved' : 'pending',
                'reviewed_by' => $isAdmin ? $request->user()->id : null,
                'reviewed_at' => $isAdmin ? now() : null,
            ]);

            if ($isAdmin) {
                $this->syncCourseBrochure($course);
            } else {
                NotifyStaff::dispatch(
                    'super_admins',
                    'course_material',
                    "New {$this->label($material)} uploaded for \"{$course->title}\" - awaiting review.",
                    '/admin/materials'
                );
            }

            return response()->json([
                'status'  => 201,
                'message' => $isAdmin ? 'Uploaded and live.' : 'Uploaded - a super admin will review it shortly.',
                'data'    => $material->load(['module:id,title,order_index', 'uploader:id,name,email']),
            ], 201);
        } catch (\Exception $e) {
            Log::error('CourseMaterialController@store: ' . $e->getMessage());
            return response()->json(['status' => 500, 'message' => 'An error occurred while uploading the file.'], 500);
        }
    }

    public function setStatus(Request $request, string $id)
    {
        if (!$this->isSuperAdminRequest($request)) {
            return response()->json(['status' => 403, 'message' => 'Forbidden.'], 403);
        }

        $status = $request->input('status');

        if (!in_array($status, ['approved', 'rejected'], true)) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => ['status' => ['Must be approved or rejected.']],
            ], 422);
        }

        if ($status === 'rejected' && !trim((string) $request->input('rejection_reason'))) {
            return response()->json([
                'status'  => 422,
                'message' => 'Please give a reason for rejecting.',
                'errors'  => ['rejection_reason' => ['Please give a reason for rejecting.']],
            ], 422);
        }

        $material = CourseMaterial::with('course')->find($id);

        if (!$material) {
            return response()->json(['status' => 404, 'message' => 'Material not found.'], 404);
        }

        try {
            $material->forceFill([
                'status' => $status,
                'rejection_reason' => $status === 'rejected' ? trim($request->input('rejection_reason')) : null,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ])->save();

            $this->syncCourseBrochure($material->course);

            AdminAuditLog::create([
                'admin_id' => $request->user()->id,
                'action' => $status === 'approved' ? 'approve_course_material' : 'reject_course_material',
                'target_type' => 'course_material',
                'target_id' => $material->id,
                'notes' => $material->rejection_reason,
            ]);

            $label = $this->label($material);
            NotificationService::notifyUser(
                $material->uploaded_by,
                'course_material',
                $status === 'approved'
                    ? "Your {$label} for \"{$material->course->title}\" was approved and is now live."
                    : "Your {$label} for \"{$material->course->title}\" was rejected. Reason: {$material->rejection_reason}",
                "/mentors/courses/{$material->course_id}"
            );

            return response()->json([
                'status'  => 200,
                'message' => "Material {$status}.",
                'data'    => $material->fresh(['course:id,title,code', 'module:id,title,order_index', 'uploader:id,name,email']),
            ], 200);
        } catch (\Exception $e) {
            Log::error('CourseMaterialController@setStatus: ' . $e->getMessage());
            return response()->json(['status' => 500, 'message' => 'An error occurred while reviewing the material.'], 500);
        }
    }

    public function delete(Request $request, string $id)
    {
        $material = CourseMaterial::with('course')->find($id);

        if (!$material) {
            return response()->json(['status' => 404, 'message' => 'Material not found.'], 404);
        }

        $isUploader = (string) $material->uploaded_by === (string) $request->user()->id;

        // Mentors can withdraw their own uploads until they're approved;
        // removing a live file is an admin decision.
        if (!$this->isAdminRequest($request) && !($isUploader && $material->status !== 'approved')) {
            return response()->json(['status' => 403, 'message' => 'Only an admin can remove an approved file.'], 403);
        }

        $material->delete();
        $this->syncCourseBrochure($material->course);

        return response()->json(['status' => 200, 'message' => 'Removed.'], 200);
    }

    /**
     * courses.brochure_url always points at the latest approved brochure -
     * it's what brochure-request emails link to.
     */
    private function syncCourseBrochure(?Course $course): void
    {
        if (!$course) {
            return;
        }

        $latest = CourseMaterial::where('course_id', $course->id)
            ->where('type', 'brochure')
            ->where('status', 'approved')
            ->latest('reviewed_at')
            ->value('file_url');

        if ($course->brochure_url !== $latest) {
            $course->forceFill(['brochure_url' => $latest])->save();
        }
    }

    private function label(CourseMaterial $material): string
    {
        return match ($material->type) {
            'course_content' => 'course content PDF',
            'brochure' => 'brochure',
            default => 'slides' . ($material->module ? " for \"{$material->module->title}\"" : ''),
        };
    }
}

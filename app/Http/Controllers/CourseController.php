<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\Controller;
use App\Helpers\Utilities;
use App\Helpers\Validations;
use App\Models\AdminAuditLog;
use App\Models\Cohort;
use App\Models\Course;
use App\Models\CourseRating;
use App\Models\Enrollment;
use App\Models\InstructorProfile;
use App\Models\LessonProgress;
use App\Models\ScholarUser;
use App\Services\ModuleAccessService;
use App\Services\NotificationService;

class CourseController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = Course::with(['category', 'pace.certificationLevel.certificationType', 'mentor'])
                ->withCount(['enrollments', 'ratings']);

            if ($q = trim($request->input('q', ''))) {
                $query->where('title', 'like', '%' . $q . '%');
            }

            if ($category = trim($request->input('category', ''))) {
                $query->whereHas('category', function ($q) use ($category) {
                    $q->where('slug', $category);
                });
            }

            if ($certificationLevel = trim($request->input('certification_level', ''))) {
                $query->whereHas('pace.certificationLevel', function ($q) use ($certificationLevel) {
                    $q->where('slug', $certificationLevel);
                });
            }

            if ($certificationType = trim($request->input('certification_type', ''))) {
                $query->whereHas('pace.certificationLevel.certificationType', function ($q) use ($certificationType) {
                    $q->where('slug', $certificationType);
                });
            }

            if ($classification = trim($request->input('classification', ''))) {
                $query->where('classification', $classification);
            }

            $query->where('status', 'published')->where('admin_approval_status', 'approved');

            $results = $query->orderBy('title', 'asc')->paginate(9);

            return response()->json([
                'status' => 200,
                'data'   => $results,
            ], 200);
        } catch (\Exception $e) {
            Log::error('CourseController@index: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving courses.',
            ], 500);
        }
    }

    public function summary(Request $request)
    {
        try {
            $instructorId = $request->user()->id;

            $courseIds = Course::where('instructor_id', $instructorId)->pluck('id');

            $totalCourses = $courseIds->count();
            $publishedCourses = Course::where('instructor_id', $instructorId)
                ->where('status', 'published')
                ->count();

            $totalRegistrations = Enrollment::whereIn('course_id', $courseIds)->count();

            $averageRating = CourseRating::whereIn('course_id', $courseIds)->avg('rating');

            $upcomingCohorts = Cohort::with('course:id,title')
                ->whereIn('course_id', $courseIds)
                ->where('start_date', '>=', now()->toDateString())
                ->orderBy('start_date', 'asc')
                ->limit(3)
                ->get(['id', 'course_id', 'label', 'start_date', 'capacity', 'seats_taken']);

            $topCourses = Course::where('instructor_id', $instructorId)
                ->withCount('enrollments')
                ->orderBy('enrollments_count', 'desc')
                ->limit(3)
                ->get(['id', 'title', 'slug']);

            return response()->json([
                'status' => 200,
                'data' => [
                    'total_courses' => $totalCourses,
                    'published_courses' => $publishedCourses,
                    'total_registrations' => $totalRegistrations,
                    'average_rating' => $averageRating ? round($averageRating, 1) : null,
                    'upcoming_cohorts' => $upcomingCohorts,
                    'top_courses' => $topCourses,
                ],
            ], 200);
        } catch (\Exception $e) {
            Log::error('CourseController@summary: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving the summary.',
            ], 500);
        }
    }

    public function curriculum(Request $request, string $id)
    {
        try {
            $course = Course::with('instructor')->find($id);

            if (!$course) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Course not found.',
                ], 404);
            }

            $userId = $request->user()->id;
            $user = ScholarUser::find($userId);

            $isAdmin = $user && $user->role === 'admin';
            $isOwningInstructor = $user && $user->role === 'instructor'
                && (string) $course->instructor_id === (string) $userId;

            $enrollment = Enrollment::where('course_id', $course->id)
                ->where('learner_id', $userId)
                ->first();

            if (!$isAdmin && !$isOwningInstructor && !$enrollment) {
                return response()->json([
                    'status'  => 403,
                    'message' => 'You must be enrolled in this course to view its curriculum.',
                ], 403);
            }

            $progressByLessonId = [];
            if ($enrollment) {
                $progressByLessonId = LessonProgress::where('enrollment_id', $enrollment->id)
                    ->get()
                    ->keyBy('lesson_id');
            }

            $modules = $course->modules()
                ->with([
                    'lessons' => function ($query) {
                        $query->orderBy('order_index', 'asc')->with('resources');
                    },
                    'quiz' => function ($query) {
                        $query->withCount('questions');
                    },
                ])
                ->orderBy('order_index', 'asc')
                ->get();

            $bypassesGating = $isAdmin || $isOwningInstructor || !$enrollment;

            $modules->each(function ($module) use ($progressByLessonId, $enrollment, $bypassesGating) {
                $access = $bypassesGating
                    ? ['accessible' => true, 'reason' => null]
                    : ModuleAccessService::checkModuleAccess($enrollment, $module);

                $module->is_accessible = $access['accessible'];
                $module->lock_reason = $access['reason'];
                $module->unlock_date = $enrollment
                    ? ModuleAccessService::unlockDateFor($enrollment, $module)?->toDateString()
                    : null;
                $module->is_passed = $enrollment ? ModuleAccessService::isModulePassed($enrollment, $module) : null;

                $module->lessons->each(function ($lesson) use ($progressByLessonId, $access) {
                    $progress = $progressByLessonId[$lesson->id] ?? null;
                    $lesson->progress_status = $progress->status ?? 'not_started';
                    $lesson->progress_id = $progress->id ?? null;

                    if (!$access['accessible']) {
                        $lesson->content_url_or_body = null;
                    }
                });
            });

            return response()->json([
                'status' => 200,
                'data' => [
                    'course' => $course,
                    'enrollment' => $enrollment,
                    'modules' => $modules,
                ],
            ], 200);
        } catch (\Exception $e) {
            Log::error('CourseController@curriculum: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving the curriculum.',
            ], 500);
        }
    }

    public function mine(Request $request)
    {
        try {
            $query = Course::with(['category', 'pace.certificationLevel.certificationType', 'mentor'])
                ->withCount(['enrollments', 'ratings'])
                ->where('instructor_id', $request->user()->id);

            if ($q = trim($request->input('q', ''))) {
                $query->where('title', 'like', '%' . $q . '%');
            }

            if ($status = trim($request->input('status', ''))) {
                $query->where('status', $status);
            }

            $results = $query->orderBy('created_at', 'desc')->paginate(10);

            return response()->json([
                'status' => 200,
                'data'   => $results,
            ], 200);
        } catch (\Exception $e) {
            Log::error('CourseController@mine: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving your courses.',
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
            $query = Course::with(['category', 'instructor'])
                ->withCount(['enrollments', 'ratings']);

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
            Log::error('CourseController@forReview: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving courses for review.',
            ], 500);
        }
    }

    public function popular(Request $request)
    {
        try {
            $limit = (int) $request->input('limit', 6);
            $limit = $limit > 0 && $limit <= 24 ? $limit : 6;

            $results = Course::with(['category', 'pace.certificationLevel.certificationType', 'mentor'])
                ->withCount(['enrollments', 'ratings'])
                ->where('status', 'published')
                ->where('admin_approval_status', 'approved')
                ->orderByDesc('enrollments_count')
                ->orderByDesc('published_at')
                ->limit($limit)
                ->get();

            return response()->json([
                'status' => 200,
                'data'   => $results,
            ], 200);
        } catch (\Exception $e) {
            Log::error('CourseController@popular: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving popular courses.',
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

        $data = $request->all();
        $isAdmin = $user->role === 'admin';

        if (!$isAdmin || empty($data['instructor_id'])) {
            $data['instructor_id'] = $request->user()->id;
        }

        if (!$isAdmin) {
            $instructorProfile = InstructorProfile::where('user_id', $request->user()->id)->first();

            if (!$instructorProfile || $instructorProfile->approval_status !== 'approved') {
                return response()->json([
                    'status'  => 403,
                    'message' => 'Your instructor account must be approved by an admin before you can create courses.',
                ], 403);
            }
        }

        if ($request->hasFile('thumbnail')) {
            $upload = $this->uploadThumbnail($request);
            if (!$upload['success']) {
                return response()->json([
                    'status'  => 500,
                    'message' => $upload['message'],
                ], 500);
            }
            $data['thumbnail_url'] = $upload['url'];
        }

        $validator = Validations::validateCourse($data);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => $validator->messages(),
            ], 422);
        }

        try {
            $course = Course::create($data);
            $course->refresh();

            return response()->json([
                'status'  => 201,
                'message' => 'Course created successfully.',
                'data'    => $course,
            ], 201);
        } catch (\Exception $e) {
            Log::error('CourseController@store: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while creating the course.',
            ], 500);
        }
    }

    public function show(Request $request, string $id)
    {
        try {
            $course = Course::with(['category', 'instructor', 'pace.certificationLevel.certificationType', 'mentor'])
                ->withCount(['enrollments', 'ratings'])
                ->find($id);

            if (!$course) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Course not found.',
                ], 404);
            }

            // This route has no auth:sanctum middleware (it's the public course-detail
            // page), so $request->user() is never populated even with a valid Bearer
            // token - resolve the sanctum guard directly so a logged-in caller is still
            // recognized. Instructor/mentor identity is only revealed to the owning
            // instructor, an admin, or a learner already enrolled in this course -
            // everyone else (including anonymous visitors) sees the course without it
            // until they enroll.
            $authUser = $request->user('sanctum');
            $user = $authUser ? ScholarUser::find($authUser->id) : null;

            $isAdmin = $user && $user->role === 'admin';
            $isOwningInstructor = $user && $user->role === 'instructor'
                && (string) $course->instructor_id === (string) $authUser->id;
            $isEnrolled = $authUser && Enrollment::where('course_id', $course->id)
                ->where('learner_id', $authUser->id)
                ->exists();

            if (!$isAdmin && !$isOwningInstructor && !$isEnrolled) {
                $course->setRelation('mentor', null);
                $course->setRelation('instructor', null);
            }

            return response()->json([
                'status' => 200,
                'data'   => $course,
            ], 200);
        } catch (\Exception $e) {
            Log::error('CourseController@show: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving the course.',
            ], 500);
        }
    }

    public function update(Request $request, string $id)
    {
        try {
            $course = Course::find($id);

            if (!$course) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Course not found.',
                ], 404);
            }

            $user = ScholarUser::find($request->user()->id);

            $isAdmin = $user && $user->role === 'admin';
            $isOwningInstructor = $user && $user->role === 'instructor' && (string) $course->instructor_id === (string) $request->user()->id;

            if (!$isAdmin && !$isOwningInstructor) {
                return response()->json([
                    'status'  => 403,
                    'message' => 'Forbidden.',
                ], 403);
            }

            $data = $request->all();

            if ($request->hasFile('thumbnail')) {
                $upload = $this->uploadThumbnail($request);
                if (!$upload['success']) {
                    return response()->json([
                        'status'  => 500,
                        'message' => $upload['message'],
                    ], 500);
                }
                $data['thumbnail_url'] = $upload['url'];
            }

            $validator = Validations::validateCourse($data, $id);

            if ($validator->fails()) {
                return response()->json([
                    'status'  => 422,
                    'message' => 'Validation failed.',
                    'errors'  => $validator->messages(),
                ], 422);
            }

            if (!$isAdmin) {
                unset($data['instructor_id']);
            }

            $course->update($data);

            return response()->json([
                'status'  => 200,
                'message' => 'Course updated successfully.',
                'data'    => $course,
            ], 200);
        } catch (\Exception $e) {
            Log::error('CourseController@update: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while updating the course.',
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
            $course = Course::find($id);

            if (!$course) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Course not found.',
                ], 404);
            }

            $course->forceFill([
                'admin_approval_status' => $status,
                'admin_rejection_reason' => $status === 'rejected' ? $request->input('admin_rejection_reason') : null,
            ])->save();

            $actionByStatus = [
                'approved' => 'approve_course',
                'rejected' => 'reject_course',
                'pending' => 'reset_course_approval',
            ];

            AdminAuditLog::create([
                'admin_id' => $request->user()->id,
                'action' => $actionByStatus[$status],
                'target_type' => 'course',
                'target_id' => $course->id,
                'notes' => $status === 'rejected' ? $course->admin_rejection_reason : null,
            ]);

            if ($status === 'approved') {
                NotificationService::notifyUser(
                    $course->instructor_id,
                    'course_review',
                    "Your course \"{$course->title}\" has been approved and is now live."
                );
            } elseif ($status === 'rejected') {
                NotificationService::notifyUser(
                    $course->instructor_id,
                    'course_review',
                    "Your course \"{$course->title}\" was rejected." . ($course->admin_rejection_reason ? " Reason: {$course->admin_rejection_reason}" : '')
                );
            }

            return response()->json([
                'status'  => 200,
                'message' => 'Course approval status updated successfully.',
                'data'    => $course,
            ], 200);
        } catch (\Exception $e) {
            Log::error('CourseController@setApprovalStatus: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while updating the approval status.',
            ], 500);
        }
    }

    public function delete(Request $request, string $id)
    {
        try {
            $course = Course::find($id);

            if (!$course) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Course not found.',
                ], 404);
            }

            $user = ScholarUser::find($request->user()->id);

            $isAdmin = $user && $user->role === 'admin';
            $isOwningInstructor = $user && $user->role === 'instructor' && (string) $course->instructor_id === (string) $request->user()->id;

            if (!$isAdmin && !$isOwningInstructor) {
                return response()->json([
                    'status'  => 403,
                    'message' => 'Forbidden.',
                ], 403);
            }

            $course->delete();

            return response()->json([
                'status'  => 200,
                'message' => 'Course deleted successfully.',
            ], 200);
        } catch (\Exception $e) {
            Log::error('CourseController@delete: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while deleting the course.',
            ], 500);
        }
    }

    private function uploadThumbnail(Request $request): array
    {
        $upload = Utilities::uploadFile($request->file('thumbnail'), 'course-thumbnails');

        if ($upload['status'] !== 200) {
            return ['success' => false, 'message' => $upload['message']];
        }

        return ['success' => true, 'url' => Storage::url($upload['path'])];
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\Controller;
use App\Helpers\Utilities;
use App\Helpers\Validations;
use App\Models\AdminAuditLog;
use App\Models\Cohort;
use App\Models\Category;
use App\Models\CohortMentorApplication;
use App\Models\InstructorProfile;
use App\Models\Course;
use App\Models\CourseTool;
use App\Models\CourseRating;
use App\Models\Enrollment;
use App\Models\LessonProgress;
use App\Models\ScholarUser;
use App\Services\ModuleAccessService;
use App\Services\CourseCatalogue;
use App\Services\LifecycleNotifier;
use App\Services\NotificationService;
use App\Traits\AuthorizesCourseOwnership;

class CourseController extends Controller
{
    use AuthorizesCourseOwnership;

    public function index(Request $request)
    {
        try {
            $catalogue = new CourseCatalogue($request->only(CourseCatalogue::FILTERS));

            if ($request->boolean('compact')) {
                return response()->json([
                    'status' => 200,
                    'data'   => $catalogue->compact(min(max((int) $request->input('per_page', 50), 1), 200)),
                ], 200);
            }

            $perPage = min(max((int) $request->input('per_page', 12), 1), 48);
            $results = $catalogue->paginate((string) $request->input('sort', 'title'), $perPage);

            $results->getCollection()->each(function ($course) {
                $course->from_price = $course->from_price !== null ? (int) $course->from_price : null;
                $course->licence_total = (int) $course->licence_total;
                $course->physical_cohorts_count = (int) $course->physical_cohorts_count;
                $course->virtual_cohorts_count = (int) $course->virtual_cohorts_count;
            });

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

    public function facets(Request $request)
    {
        try {
            $catalogue = new CourseCatalogue($request->only(CourseCatalogue::FILTERS));

            return response()->json([
                'status' => 200,
                'data'   => $catalogue->facets($request->boolean('include_empty')),
            ], 200);
        } catch (\Exception $e) {
            Log::error('CourseController@facets: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving course filters.',
            ], 500);
        }
    }

    /**
     * Replace the tools (and their licence prices) a course uses. Admin-only.
     */
    public function syncTools(Request $request, string $id)
    {
        if (!$this->isAdminRequest($request)) {
            return response()->json(['status' => 403, 'message' => 'Forbidden.'], 403);
        }

        $course = Course::find($id);
        if (!$course) {
            return response()->json(['status' => 404, 'message' => 'Course not found.'], 404);
        }

        $validator = Validations::validateCourseTools($request->all());
        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => $validator->messages(),
            ], 422);
        }

        try {
            DB::transaction(function () use ($course, $request) {
                $wanted = collect($request->input('tools', []))->keyBy('tool_id');

                $course->courseTools()->whereNotIn('tool_id', $wanted->keys())->delete();

                foreach ($wanted as $toolId => $row) {
                    CourseTool::updateOrCreate(
                        ['course_id' => $course->id, 'tool_id' => $toolId],
                        ['licence_price' => $row['licence_price'] ?? null]
                    );
                }
            });

            $course->load('tools');

            LifecycleNotifier::courseUpdated($course, ScholarUser::findOrFail($request->user()->id), ['tools']);

            return response()->json([
                'status'  => 200,
                'message' => 'Course tools updated successfully.',
                'data'    => [
                    'tools' => $course->tools,
                    'licence_total' => $course->licenceTotal(),
                ],
            ], 200);
        } catch (\Exception $e) {
            Log::error('CourseController@syncTools: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while updating the course tools.',
            ], 500);
        }
    }

    /**
     * Instructors approved to mentor a cohort, as learners see them.
     */
    private function cohortMentors(?string $cohortId): array
    {
        if (!$cohortId) {
            return [];
        }

        return CohortMentorApplication::with('instructor:id,name')
            ->where('cohort_id', $cohortId)
            ->where('status', 'approved')
            ->orderBy('reviewed_at')
            ->get()
            ->map(function ($application) {
                $profile = InstructorProfile::where('user_id', $application->instructor_id)
                    ->first(['bio', 'specialization_one', 'specialization_two']);

                return [
                    'name' => $application->instructor?->name,
                    'title' => collect([$profile?->specialization_one, $profile?->specialization_two])->filter()->implode(' · ') ?: null,
                    'bio' => $profile?->bio,
                ];
            })
            ->filter(fn ($mentor) => $mentor['name'])
            ->values()
            ->all();
    }

    /**
     * A sub-distinction belongs to one distinction - keep a course's
     * category inside its classification.
     */
    private function categoryMismatch(array $data, ?Course $course = null): ?string
    {
        $categoryId = array_key_exists('category_id', $data) ? $data['category_id'] : $course?->category_id;
        $classification = $data['classification'] ?? $course?->classification ?? 'skills_professional';

        if (!$categoryId) {
            return null;
        }

        $category = Category::find($categoryId);

        return $category && $category->classification !== $classification
            ? "\"{$category->name}\" belongs to a different level. Pick a sub-distinction under the course's level."
            : null;
    }

    public function summary(Request $request)
    {
        try {
            $instructorId = $request->user()->id;

            $courseIds = Course::manageableBy($instructorId)->pluck('id');

            $totalCourses = $courseIds->count();
            $publishedCourses = Course::manageableBy($instructorId)
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

            $topCourses = Course::manageableBy($instructorId)
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

            $isAdmin = $user && $user->isAdmin();
            $isOwningInstructor = $user && $user->role === 'instructor'
                && $course->isManageableBy($userId);

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
                    'lessons' => function ($query) use ($isAdmin, $isOwningInstructor) {
                        // Reviewers see every lesson; learners only approved ones.
                        if (!$isAdmin && !$isOwningInstructor) {
                            $query->approved();
                        }
                        $query->orderBy('order_index', 'asc')->with('resources');
                    },
                    'quiz' => function ($query) {
                        $query->withCount('questions');
                    },
                ])
                ->orderBy('order_index', 'asc')
                ->get();

            $bypassesGating = $isAdmin || $isOwningInstructor || !$enrollment;

            // The owner is the super admin account - not something learners need.
            if (!$isAdmin && !$isOwningInstructor) {
                $course->setRelation('instructor', null);
            }

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
                ->withCount(['enrollments', 'ratings']);

            // Admins manage the whole (single-account) catalogue; instructors see
            // the courses they're approved to mentor.
            if (!$this->isAdminRequest($request)) {
                $query->manageableBy($request->user()->id);
            }

            if ($q = trim($request->input('q', ''))) {
                $query->where('title', 'like', '%' . $q . '%');
            }

            if ($status = trim($request->input('status', ''))) {
                $query->where('status', $status);
            }

            $results = $query->orderBy('created_at', 'desc')->paginate(min(max((int) $request->input('per_page', 10), 1), 100));

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

        if (!$user || !$user->isAdmin()) {
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

        // Courses are centrally managed by the super admin. Instructors take
        // part by applying to mentor a cohort, not by creating courses.
        if (!$user || !$user->isAdmin()) {
            return response()->json([
                'status'  => 403,
                'message' => 'Only an admin can create courses. Instructors can apply to mentor a cohort instead.',
            ], 403);
        }

        $data = $request->all();
        $data['instructor_id'] = Course::superAdminId() ?? $request->user()->id;

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

        if ($mismatch = $this->categoryMismatch($data)) {
            return response()->json([
                'status'  => 422,
                'message' => $mismatch,
                'errors'  => ['category_id' => [$mismatch]],
            ], 422);
        }

        try {
            $course = Course::create($data);
            // A super admin's course goes live; an admin's waits for one to approve it.
            $isSuperAdmin = $user->isSuperAdmin();
            $course->forceFill(['admin_approval_status' => $isSuperAdmin ? 'approved' : 'pending'])->save();
            $course->refresh();

            if (!$isSuperAdmin) {
                NotificationService::notifySuperAdmins(
                    'course_review',
                    "{$user->email} created the course \"{$course->title}\" - it needs approval before it goes live.",
                    '/admin/courses'
                );
            }

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
            $course = Course::with(['category', 'instructor', 'pace.certificationLevel.certificationType', 'mentor', 'tools'])
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
            // recognized.
            $authUser = $request->user('sanctum');
            $user = $authUser ? ScholarUser::find($authUser->id) : null;

            $isAdmin = $user && $user->isAdmin();
            $isOwningInstructor = $user && $user->role === 'instructor'
                && $course->isManageableBy($authUser->id);

            // The course-level mentor record and owner (the super admin) are for
            // admins and the course's mentors only. Learners instead see the
            // mentors approved for their own cohort - and nothing until then.
            if (!$isAdmin && !$isOwningInstructor) {
                $course->setRelation('mentor', null);
                $course->setRelation('instructor', null);
            }

            $enrollment = $authUser
                ? Enrollment::where('course_id', $course->id)->where('learner_id', $authUser->id)->first()
                : null;

            $course->setAttribute('cohort_mentors', $enrollment ? $this->cohortMentors($enrollment->cohort_id) : []);
            $course->setAttribute('licence_total', $course->licenceTotal());

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

            $isAdmin = $user && $user->isAdmin();
            if (!$isAdmin) {
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

            // Ownership is fixed to the super admin - validate against it rather
            // than requiring every edit form to resend it.
            $data['instructor_id'] = $course->instructor_id;

            $validator = Validations::validateCourse($data, $id);

            if ($validator->fails()) {
                return response()->json([
                    'status'  => 422,
                    'message' => 'Validation failed.',
                    'errors'  => $validator->messages(),
                ], 422);
            }

            if ($mismatch = $this->categoryMismatch($data, $course)) {
                return response()->json([
                    'status'  => 422,
                    'message' => $mismatch,
                    'errors'  => ['category_id' => [$mismatch]],
                ], 422);
            }

            // Going live for the first time is timestamped, like approval-driven publishing.
            if (($data['status'] ?? null) === 'published' && !$course->published_at) {
                $data['published_at'] = now();
            }

            $course->update($data);

            // Tell the other super admins what changed (in-app only).
            LifecycleNotifier::courseUpdated($course, $user, array_keys($course->getChanges()));

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

            // The public catalogue lists published AND approved courses, so an
            // approved draft would stay invisible forever. Approving a draft
            // puts it live; archived courses stay archived.
            if ($status === 'approved' && $course->status === 'draft') {
                $course->forceFill(['status' => 'published', 'published_at' => $course->published_at ?? now()])->save();
            }

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

            $link = '/admin/courses/' . $course->id;
            if ($status === 'approved') {
                NotificationService::notifyReviewOutcome('course_review', "The course \"{$course->title}\" was approved" . ($course->status === 'published' ? ' and is now live.' : '.'), $link, $request->user()->id);
            } elseif ($status === 'rejected') {
                NotificationService::notifyReviewOutcome('course_review', "The course \"{$course->title}\" was rejected." . ($course->admin_rejection_reason ? " Reason: {$course->admin_rejection_reason}" : ''), $link, $request->user()->id);
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

            $isAdmin = $user && $user->isAdmin();
            if (!$isAdmin) {
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

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

    public function suggest(Request $request)
    {
        try {
            $catalogue = new CourseCatalogue(['q' => $request->input('q', '')]);

            return response()->json([
                'status' => 200,
                'data'   => $catalogue->suggest(5),
            ], 200);
        } catch (\Exception $e) {
            Log::error('CourseController@suggest: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while searching courses.',
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

            // Student counts are only for cohorts the instructor is an approved
            // mentor of; owning a course alone does not reveal them.
            $mentoredCohortIds = CohortMentorApplication::where('instructor_id', $instructorId)
                ->where('status', 'approved')
                ->pluck('cohort_id');

            $totalRegistrations = Enrollment::whereIn('cohort_id', $mentoredCohortIds)->count();

            $averageRating = CourseRating::whereIn('course_id', $courseIds)->avg('rating');

            $upcomingCohorts = Cohort::with('course:id,title')
                ->whereIn('course_id', $courseIds)
                ->where('start_date', '>=', now()->toDateString())
                ->orderBy('start_date', 'asc')
                ->limit(3)
                ->get(['id', 'course_id', 'label', 'start_date', 'capacity', 'seats_taken'])
                ->each(function ($cohort) use ($mentoredCohortIds) {
                    if (!$mentoredCohortIds->contains($cohort->id)) {
                        $cohort->seats_taken = null;
                    }
                });

            $topCourses = Course::manageableBy($instructorId)
                ->withCount(['enrollments' => fn ($q) => $q->whereIn('cohort_id', $mentoredCohortIds)])
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
        $user = $request->scholarUser();

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
        $user = $request->scholarUser();

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

        if ($error = $this->thumbnailError($request)) {
            return response()->json(['status' => 422, 'message' => $error, 'errors' => ['thumbnail' => [$error]]], 422);
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

            $user = $request->scholarUser();

            $isAdmin = $user && $user->isAdmin();
            if (!$isAdmin) {
                return response()->json([
                    'status'  => 403,
                    'message' => 'Forbidden.',
                ], 403);
            }

            $data = $request->all();
            $oldThumbnail = $course->thumbnail_url;

            if ($error = $this->thumbnailError($request)) {
                return response()->json(['status' => 422, 'message' => $error, 'errors' => ['thumbnail' => [$error]]], 422);
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
            } elseif ($request->boolean('remove_thumbnail')) {
                $data['thumbnail_url'] = null;
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

            // The old picture is no longer referenced once it has been replaced or removed.
            if (array_key_exists('thumbnail_url', $data) && $data['thumbnail_url'] !== $oldThumbnail) {
                $this->deleteThumbnailFile($oldThumbnail);
            }

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
        $user = $request->scholarUser();

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

            $this->applyApprovalStatus($course, $status, $request->input('admin_rejection_reason'), $request);

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

            $user = $request->scholarUser();

            $isAdmin = $user && $user->isAdmin();
            if (!$isAdmin) {
                return response()->json([
                    'status'  => 403,
                    'message' => 'Forbidden.',
                ], 403);
            }

            // Removing a course hides it from learners too, so make the admin sure about it.
            if (!$request->boolean('force') && ($enrolled = $this->activeEnrollments($course)) > 0) {
                return response()->json([
                    'status'  => 409,
                    'message' => "{$enrolled} learner(s) are enrolled in this course. Removing it hides it from them as well.",
                    'enrollments_count' => $enrolled,
                ], 409);
            }

            $course->delete();
            AdminAuditLog::create(['admin_id' => $request->user()->id, 'action' => 'remove_course', 'target_type' => 'course', 'target_id' => $course->id]);

            return response()->json([
                'status'  => 200,
                'message' => 'Course removed. It can be restored from the Removed list.',
            ], 200);
        } catch (\Exception $e) {
            Log::error('CourseController@delete: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while deleting the course.',
            ], 500);
        }
    }


    /**
     * Sets a course's approval status and everything that follows from it: approving a
     * draft puts it live, the decision is audit-logged and staff are told.
     */
    private function applyApprovalStatus(Course $course, string $status, ?string $reason, Request $request): void
    {
        $course->forceFill([
            'admin_approval_status' => $status,
            'admin_rejection_reason' => $status === 'rejected' ? $reason : null,
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
    }

    /** Courses with learners still enrolled can't be removed by accident. */
    private function activeEnrollments(Course $course): int
    {
        return $course->enrollments()->whereNotIn('enrollment_status', ['dropped'])->count();
    }

    /**
     * The admin course list: one query for the page of rows and one for the tab counts.
     * Filters are combined (search + view + level + category + needs-attention flag).
     */
    public function adminIndex(Request $request)
    {
        $user = $request->scholarUser();

        if (!$user || !$user->isAdmin()) {
            return response()->json(['status' => 403, 'message' => 'Forbidden.'], 403);
        }

        try {
            $recentDays = 30;
            $view = (string) $request->input('view', 'all');

            $query = $view === 'removed' ? Course::onlyTrashed() : Course::query();

            $query->select([
                'id', 'code', 'slug', 'title', 'tagline', 'status', 'admin_approval_status', 'admin_rejection_reason', 'classification',
                'category_id', 'certificate_kind', 'price', 'original_price', 'currency', 'thumbnail_url', 'mode', 'level',
                'duration_weeks', 'published_at', 'created_at', 'updated_at', 'deleted_at', 'instructor_id',
            ])
                ->with('category:id,name,slug,classification')
                ->withCount([
                    'enrollments as enrollments_count' => fn ($q) => $q->whereNotIn('enrollment_status', ['dropped']),
                    'cohorts as upcoming_cohorts_count' => fn ($q) => CourseCatalogue::upcoming($q),
                ]);

            match ($view) {
                'pending' => $query->where('admin_approval_status', 'pending'),
                'rejected' => $query->where('admin_approval_status', 'rejected'),
                'published' => $query->where('status', 'published'),
                'draft' => $query->where('status', 'draft'),
                'archived' => $query->where('status', 'archived'),
                'recent' => $query->where('status', 'published')->where('published_at', '>=', now()->subDays($recentDays)),
                default => null,
            };

            if ($term = CourseCatalogue::cleanTerm((string) $request->input('q', ''))) {
                // "!" is the escape character (named explicitly so it behaves the same on MySQL
                // and SQLite), which makes "100%" or "_" search for those literal characters.
                $like = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term) . '%';
                $query->where(function ($q) use ($like) {
                    foreach (['title', 'code', 'slug', 'tagline', 'short_description'] as $column) {
                        $q->orWhereRaw("{$column} like ? escape '!'", [$like]);
                    }
                    $q->orWhereHas('category', fn ($c) => $c->whereRaw("name like ? escape '!'", [$like]));
                });
            }

            foreach (['status', 'admin_approval_status', 'classification', 'category_id', 'mode', 'level', 'certificate_kind'] as $filter) {
                if (($value = trim((string) $request->input($filter, ''))) !== '') {
                    $query->where($filter, $value);
                }
            }

            // "Needs attention" shortcuts for tidying the catalogue.
            match ((string) $request->input('issue', '')) {
                'no_image' => $query->where(fn ($q) => $q->whereNull('thumbnail_url')->orWhere('thumbnail_url', '')),
                'no_category' => $query->whereNull('category_id'),
                'no_cohort' => $query->whereDoesntHave('cohorts', fn ($q) => CourseCatalogue::upcoming($q)),
                'no_price' => $query->where('price', 0),
                default => null,
            };

            match ((string) $request->input('sort', $view === 'recent' ? 'published' : 'newest')) {
                'oldest' => $query->orderBy('created_at'),
                'updated' => $query->orderByDesc('updated_at'),
                'published' => $query->orderByDesc('published_at'),
                'title' => $query->orderBy('title'),
                'code' => $query->orderBy('code'),
                'price_asc' => $query->orderBy('price'),
                'price_desc' => $query->orderByDesc('price'),
                'learners' => $query->orderByDesc('enrollments_count'),
                'removed' => $query->orderByDesc('deleted_at'),
                default => $query->orderByDesc('created_at'),
            };
            $query->orderBy('id');

            $results = $query->paginate(min(max((int) $request->input('per_page', 25), 10), 100));

            $c = Course::withTrashed()->selectRaw(
                "sum(case when deleted_at is null then 1 else 0 end) as total,
                 sum(case when deleted_at is null and admin_approval_status = 'pending' then 1 else 0 end) as pending,
                 sum(case when deleted_at is null and admin_approval_status = 'rejected' then 1 else 0 end) as rejected,
                 sum(case when deleted_at is null and status = 'published' then 1 else 0 end) as published,
                 sum(case when deleted_at is null and status = 'draft' then 1 else 0 end) as draft,
                 sum(case when deleted_at is null and status = 'archived' then 1 else 0 end) as archived,
                 sum(case when deleted_at is null and status = 'published' and published_at >= ? then 1 else 0 end) as recent,
                 sum(case when deleted_at is not null then 1 else 0 end) as removed",
                [now()->subDays($recentDays)]
            )->first();

            return response()->json([
                'status' => 200,
                'data' => $results,
                'counts' => collect(['total', 'pending', 'rejected', 'published', 'draft', 'archived', 'recent', 'removed'])
                    ->mapWithKeys(fn ($k) => [$k => (int) ($c->{$k} ?? 0)]),
                'recent_days' => $recentDays,
            ], 200);
        } catch (\Exception $e) {
            Log::error('CourseController@adminIndex: ' . $e->getMessage());
            return response()->json(['status' => 500, 'message' => 'An error occurred while loading the courses.'], 500);
        }
    }

    public function restore(Request $request, string $id)
    {
        $user = $request->scholarUser();

        if (!$user || !$user->isAdmin()) {
            return response()->json(['status' => 403, 'message' => 'Forbidden.'], 403);
        }

        $course = Course::onlyTrashed()->find($id);

        if (!$course) {
            return response()->json(['status' => 404, 'message' => 'Removed course not found.'], 404);
        }

        $course->restore();

        AdminAuditLog::create(['admin_id' => $request->user()->id, 'action' => 'restore_course', 'target_type' => 'course', 'target_id' => $course->id]);

        return response()->json(['status' => 200, 'message' => 'Course restored.', 'data' => $course], 200);
    }

    /**
     * One action across many courses (the list's checkboxes). Each course is handled on its
     * own, so one that can't be changed doesn't stop the rest; the response says which.
     */
    public function bulk(Request $request)
    {
        $user = $request->scholarUser();

        if (!$user || !$user->isAdmin()) {
            return response()->json(['status' => 403, 'message' => 'Forbidden.'], 403);
        }

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'action' => 'required|string|in:publish,draft,archive,approve,remove,restore',
            'ids' => 'required|array|min:1|max:100',
            'ids.*' => 'uuid',
            'force' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 422, 'message' => $validator->errors()->first(), 'errors' => $validator->messages()], 422);
        }

        $action = $request->input('action');

        // Approvals are a super admin decision, same as the single-course endpoint.
        if ($action === 'approve' && !$user->isSuperAdmin()) {
            return response()->json(['status' => 403, 'message' => 'Only a super admin can approve courses.'], 403);
        }

        $ids = array_values(array_unique($request->input('ids')));
        $courses = ($action === 'restore' ? Course::onlyTrashed() : Course::query())->whereIn('id', $ids)->get()->keyBy('id');

        $done = [];
        $skipped = [];

        foreach ($ids as $id) {
            $course = $courses->get($id);

            if (!$course) {
                $skipped[] = ['id' => $id, 'reason' => $action === 'restore' ? 'Not found in the removed courses.' : 'Course not found.'];
                continue;
            }

            try {
                switch ($action) {
                    case 'publish':
                        $course->forceFill(['status' => 'published', 'published_at' => $course->published_at ?? now()])->save();
                        break;
                    case 'draft':
                    case 'archive':
                        $course->forceFill(['status' => $action === 'draft' ? 'draft' : 'archived'])->save();
                        break;
                    case 'approve':
                        $this->applyApprovalStatus($course, 'approved', null, $request);
                        break;
                    case 'remove':
                        if (!$request->boolean('force') && ($n = $this->activeEnrollments($course)) > 0) {
                            $skipped[] = ['id' => $id, 'title' => $course->title, 'reason' => "{$n} learner(s) are enrolled."];
                            continue 2;
                        }
                        $course->delete();
                        AdminAuditLog::create(['admin_id' => $request->user()->id, 'action' => 'remove_course', 'target_type' => 'course', 'target_id' => $course->id]);
                        break;
                    case 'restore':
                        $course->restore();
                        AdminAuditLog::create(['admin_id' => $request->user()->id, 'action' => 'restore_course', 'target_type' => 'course', 'target_id' => $course->id]);
                        break;
                }
                $done[] = $id;
            } catch (\Exception $e) {
                Log::error("CourseController@bulk ({$action}) {$id}: " . $e->getMessage());
                $skipped[] = ['id' => $id, 'title' => $course->title, 'reason' => 'Something went wrong.'];
            }
        }

        return response()->json([
            'status' => 200,
            'message' => count($done) . ' course(s) updated' . ($skipped ? ', ' . count($skipped) . ' skipped.' : '.'),
            'data' => ['done' => $done, 'skipped' => $skipped],
        ], 200);
    }

    /** Checks an uploaded course image before it is stored: a real picture, not a script. */
    private function thumbnailError(Request $request): ?string
    {
        if (!$request->hasFile('thumbnail')) {
            return null;
        }

        $check = \Illuminate\Support\Facades\Validator::make(
            ['thumbnail' => $request->file('thumbnail')],
            ['thumbnail' => 'file|image|mimes:jpg,jpeg,png,webp|max:8192'],
            [
                'thumbnail.image' => 'The course image must be a JPG, PNG or WebP picture.',
                'thumbnail.mimes' => 'The course image must be a JPG, PNG or WebP picture.',
                'thumbnail.max' => 'The course image must be 8 MB or smaller.',
                'thumbnail.uploaded' => 'The image upload failed - it may be too large.',
            ]
        );

        return $check->fails() ? $check->errors()->first() : null;
    }

    /** Deletes a stored course image (only ones this app stored, never an outside URL). */
    private function deleteThumbnailFile(?string $url): void
    {
        if ($url && preg_match('#/storage/(course-thumbnails/[^?\#]+)$#', $url, $m)) {
            Storage::disk('public')->delete($m[1]);
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

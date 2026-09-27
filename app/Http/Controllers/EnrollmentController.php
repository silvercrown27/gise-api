<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Helpers\Validations;
use App\Models\Cohort;
use App\Models\Enrollment;
use App\Models\ScholarUser;
use App\Models\Course;

class EnrollmentController extends Controller
{
    public function index(Request $request)
    {
        try {
            $user = ScholarUser::find($request->user()->id);
            $role = $user->role ?? 'student';

            $query = Enrollment::with(['course', 'learner', 'cohort']);

            if ($role === 'student') {
                $query->where('learner_id', $request->user()->id);
            } elseif ($role === 'instructor') {
                // Mentors only see learners on courses they're approved to teach.
                $query->whereHas('course', fn ($q) => $q->manageableBy($request->user()->id));
            }

            if ($courseId = trim($request->input('course_id', ''))) {
                if ($role === 'instructor') {
                    $course = Course::find($courseId);
                    if (!$course || !$course->isManageableBy($request->user()->id)) {
                        return response()->json([
                            'status'  => 403,
                            'message' => 'Forbidden.',
                        ], 403);
                    }
                }

                $query->where('course_id', $courseId);
            }

            if ($cohortId = trim($request->input('cohort_id', ''))) {
                $query->where('cohort_id', $cohortId);
            }

            // Admin-only: lets the admin students view look up one learner's full
            // enrollment history.
            if ($role === 'admin' && $learnerId = trim($request->input('learner_id', ''))) {
                $query->where('learner_id', $learnerId);
            }

            $perPage = min(max((int) $request->input('per_page', 10), 1), 100);
            $results = $query->orderBy('created_at', 'desc')->paginate($perPage);

            return response()->json([
                'status' => 200,
                'data'   => $results,
            ], 200);
        } catch (\Exception $e) {
            Log::error('EnrollmentController@index: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving enrollments.',
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $user = ScholarUser::find($request->user()->id);
        $isAdmin = $user && $user->role === 'admin';

        $data = $request->all();
        // The fee is always worked out here, never taken from the client.
        unset($data['quoted_fee'], $data['currency']);

        if (!$isAdmin) {
            unset($data['enrollment_status'], $data['progress_percent'], $data['completed_at'], $data['enrolled_at'], $data['failed_module_id']);
            // Only an admin may enroll someone other than themselves.
            $data['learner_id'] = $request->user()->id;
        }

        $validator = Validations::validateEnrollment($data);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => $validator->messages(),
            ], 422);
        }

        try {
            $course = Course::find($data['course_id']);
            $cohort = !empty($data['cohort_id']) ? Cohort::find($data['cohort_id']) : null;

            if ($cohort && (string) $cohort->course_id !== (string) $course->id) {
                return $this->unprocessable('The selected cohort does not belong to this course.', 'cohort_id');
            }

            if (!$cohort && !$isAdmin && $course->cohorts()->exists()) {
                return $this->unprocessable('Please choose a cohort to join.', 'cohort_id');
            }

            // Admins can place a learner in any cohort (e.g. a late joiner after
            // the start date); everyone else must respect the registration rules.
            if ($cohort && !$isAdmin && ($reason = $cohort->registrationClosedReason())) {
                return $this->unprocessable($reason, 'cohort_id');
            }

            $existing = Enrollment::withTrashed()
                ->where('learner_id', $data['learner_id'])
                ->where('course_id', $course->id)
                ->first();

            if ($existing && !$existing->trashed() && $existing->enrollment_status !== 'dropped') {
                return $this->unprocessable(
                    $isAdmin ? 'This learner is already enrolled in this course.' : 'You are already enrolled in this course.',
                    'course_id'
                );
            }

            if ($course->max_students !== null) {
                $activeEnrollments = Enrollment::where('course_id', $course->id)
                    ->where('enrollment_status', '!=', 'dropped')
                    ->count();

                if ($activeEnrollments >= $course->max_students) {
                    return $this->unprocessable('This course has reached its maximum number of students.');
                }
            }

            // Licences only apply to courses that actually use tools.
            $withLicences = filter_var($data['with_licences'] ?? false, FILTER_VALIDATE_BOOLEAN)
                && $course->tools()->exists();

            $attributes = array_merge([
                'enrollment_status' => 'active',
                'enrolled_at' => now(),
            ], array_intersect_key($data, array_flip((new Enrollment)->getFillable())), [
                'with_licences' => $withLicences,
                'quoted_fee' => ($cohort ? $cohort->effectiveFee() : (int) $course->price)
                    + ($withLicences ? $course->licenceTotal() : 0),
                'currency' => $course->currency ?? 'USD',
            ]);

            // The (learner, course) pair is unique at the database level, soft
            // deletes included, so a learner coming back after being dropped or
            // removed re-activates their old row rather than hitting a 500.
            $enrollment = DB::transaction(function () use ($existing, $attributes) {
                if (!$existing) {
                    return Enrollment::create($attributes);
                }

                if ($existing->trashed()) {
                    $existing->restore();
                }

                $existing->update(array_merge(['completed_at' => null, 'failed_module_id' => null], $attributes));

                return $existing;
            });

            $enrollment->refresh();

            return response()->json([
                'status'  => 201,
                'message' => 'Enrollment created successfully.',
                'data'    => $enrollment,
            ], 201);
        } catch (\Exception $e) {
            Log::error('EnrollmentController@store: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while creating the enrollment.',
            ], 500);
        }
    }

    public function show(Request $request, string $id)
    {
        try {
            $enrollment = Enrollment::with(['course', 'learner', 'cohort'])->find($id);

            if (!$enrollment) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Enrollment not found.',
                ], 404);
            }

            if (!$this->canView($request, $enrollment)) {
                return response()->json([
                    'status'  => 403,
                    'message' => 'Forbidden.',
                ], 403);
            }

            return response()->json([
                'status' => 200,
                'data'   => $enrollment,
            ], 200);
        } catch (\Exception $e) {
            Log::error('EnrollmentController@show: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving the enrollment.',
            ], 500);
        }
    }

    /**
     * Admins can change anything about an enrollment - move a learner to
     * another cohort, fix the enrolled date, change status. A course's mentors
     * may only record progress/outcome. Learners can't edit their enrollment.
     */
    public function update(Request $request, string $id)
    {
        $validator = Validations::validateEnrollmentUpdate($request->all());

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => $validator->messages(),
            ], 422);
        }

        try {
            $user = ScholarUser::find($request->user()->id);
            $enrollment = Enrollment::find($id);

            if (!$enrollment) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Enrollment not found.',
                ], 404);
            }

            $isAdmin = $user && $user->role === 'admin';
            $isMentor = $user && $user->role === 'instructor'
                && $enrollment->course?->isManageableBy($request->user()->id);

            if (!$isAdmin && !$isMentor) {
                return response()->json([
                    'status'  => 403,
                    'message' => 'Forbidden.',
                ], 403);
            }

            $editable = $isAdmin
                ? ['cohort_id', 'enrollment_status', 'failed_module_id', 'progress_percent', 'enrolled_at', 'completed_at']
                : ['enrollment_status', 'failed_module_id', 'progress_percent', 'completed_at'];

            $data = array_intersect_key($request->all(), array_flip($editable));

            if (!empty($data['cohort_id'])) {
                $cohort = Cohort::find($data['cohort_id']);

                if ((string) $cohort->course_id !== (string) $enrollment->course_id) {
                    return $this->unprocessable('The selected cohort does not belong to this course.', 'cohort_id');
                }
            }

            $enrollment->update($data);
            $enrollment->load(['course', 'learner', 'cohort']);

            return response()->json([
                'status'  => 200,
                'message' => 'Enrollment updated successfully.',
                'data'    => $enrollment,
            ], 200);
        } catch (\Exception $e) {
            Log::error('EnrollmentController@update: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while updating the enrollment.',
            ], 500);
        }
    }

    public function delete(Request $request, string $id)
    {
        try {
            $user = ScholarUser::find($request->user()->id);
            $enrollment = Enrollment::find($id);

            if (!$enrollment) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Enrollment not found.',
                ], 404);
            }

            $isAdmin = $user && $user->role === 'admin';
            $isOwner = (string) $enrollment->learner_id === (string) $request->user()->id;

            if (!$isAdmin && !$isOwner) {
                return response()->json([
                    'status'  => 403,
                    'message' => 'Forbidden.',
                ], 403);
            }

            $enrollment->delete();

            return response()->json([
                'status'  => 200,
                'message' => 'Enrollment deleted successfully.',
            ], 200);
        } catch (\Exception $e) {
            Log::error('EnrollmentController@delete: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while deleting the enrollment.',
            ], 500);
        }
    }

    private function canView(Request $request, Enrollment $enrollment): bool
    {
        $user = ScholarUser::find($request->user()->id);

        if ((string) $enrollment->learner_id === (string) $request->user()->id) {
            return true;
        }

        if ($user && $user->role === 'admin') {
            return true;
        }

        return $user && $user->role === 'instructor'
            && $enrollment->course?->isManageableBy($request->user()->id);
    }

    private function unprocessable(string $message, ?string $field = null)
    {
        return response()->json(array_filter([
            'status'  => 422,
            'message' => $message,
            'errors'  => $field ? [$field => [$message]] : null,
        ]), 422);
    }
}

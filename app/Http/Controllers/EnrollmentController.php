<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Helpers\Validations;
use App\Models\Cohort;
use App\Models\Enrollment;
use App\Services\CourseRegistration;
use App\Services\LifecycleNotifier;
use App\Models\ScholarUser;
use App\Models\Course;

class EnrollmentController extends Controller
{
    public function index(Request $request)
    {
        try {
            $user = ScholarUser::find($request->user()->id);
            // Super admins see everything admins do.
            $role = $user?->isAdmin() ? 'admin' : ($user->role ?? 'student');

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
        $isAdmin = $user && $user->isAdmin();

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

            if ($blocked = CourseRegistration::blockedReason($data['learner_id'], $course, $cohort, $isAdmin)) {
                return $this->unprocessable(...$blocked);
            }

            $withLicences = filter_var($data['with_licences'] ?? false, FILTER_VALIDATE_BOOLEAN);

            // Paid places are only granted once Paystack confirms the charge
            // (PaystackController); learners enroll directly only when it's free.
            if (!$isAdmin && CourseRegistration::quote($course, $cohort, $withLicences)['fee'] > 0) {
                return response()->json([
                    'status'  => 402,
                    'message' => 'Please complete payment to register for this course.',
                ], 402);
            }

            $enrollment = CourseRegistration::enroll(
                $data['learner_id'],
                $course,
                $cohort,
                $withLicences,
                array_intersect_key($data, array_flip((new Enrollment)->getFillable()))
            );

            LifecycleNotifier::registered($enrollment);

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

            $isAdmin = $user && $user->isAdmin();
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

            $isAdmin = $user && $user->isAdmin();
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

        if ($user && $user->isAdmin()) {
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

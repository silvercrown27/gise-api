<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Helpers\Validations;
use App\Models\Enrollment;
use App\Models\ScholarUser;
use App\Models\Course;

class EnrollmentController extends Controller
{
    public function index(Request $request)
    {
        try {
            $user = ScholarUser::find($request->user()->id);

            $query = Enrollment::with(['course', 'learner']);

            if (!$user || $user->role === 'student') {
                $query->where('learner_id', $request->user()->id);
            }

            if ($courseId = trim($request->input('course_id', ''))) {
                if ($user && $user->role === 'instructor') {
                    $course = Course::find($courseId);
                    if (!$course || (string) $course->instructor_id !== (string) $request->user()->id) {
                        return response()->json([
                            'status'  => 403,
                            'message' => 'Forbidden.',
                        ], 403);
                    }
                }

                $query->where('course_id', $courseId);
            }

            $results = $query->orderBy('created_at', 'desc')->paginate(10);

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
        $validator = Validations::validateEnrollment($request->all());

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => $validator->messages(),
            ], 422);
        }

        try {
            $user = ScholarUser::find($request->user()->id);
            $isElevated = $user && in_array($user->role, ['instructor', 'admin']);

            $data = $request->all();

            if (!$isElevated) {
                unset($data['enrollment_status'], $data['progress_percent'], $data['completed_at']);
            }

            $course = Course::find($data['course_id'] ?? null);

            if ($course && $course->max_students !== null) {
                $activeEnrollments = Enrollment::where('course_id', $course->id)
                    ->where('enrollment_status', '!=', 'dropped')
                    ->count();

                if ($activeEnrollments >= $course->max_students) {
                    return response()->json([
                        'status'  => 422,
                        'message' => 'This course has reached its maximum number of students.',
                    ], 422);
                }
            }

            $enrollment = Enrollment::create($data);

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
            $user = ScholarUser::find($request->user()->id);
            $enrollment = Enrollment::find($id);

            if (!$enrollment) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Enrollment not found.',
                ], 404);
            }

            $isElevated = $user && in_array($user->role, ['instructor', 'admin']);
            $isOwner = (string) $enrollment->learner_id === (string) $request->user()->id;

            if (!$isElevated && !$isOwner) {
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

    public function update(Request $request, string $id)
    {
        $validator = Validations::validateEnrollment($request->all());

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

            $isElevated = $user && in_array($user->role, ['instructor', 'admin']);
            $isOwner = (string) $enrollment->learner_id === (string) $request->user()->id;

            if (!$isElevated && !$isOwner) {
                return response()->json([
                    'status'  => 403,
                    'message' => 'Forbidden.',
                ], 403);
            }

            $data = $request->all();

            if (!$isElevated) {
                unset($data['enrollment_status'], $data['progress_percent'], $data['completed_at']);
            }

            $enrollment->update($data);

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

            $isElevated = $user && in_array($user->role, ['instructor', 'admin']);
            $isOwner = (string) $enrollment->learner_id === (string) $request->user()->id;

            if (!$isElevated && !$isOwner) {
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
}

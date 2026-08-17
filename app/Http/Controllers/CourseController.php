<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Helpers\Validations;
use App\Models\Course;
use App\Models\ScholarUser;

class CourseController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = Course::withCount(['enrollments', 'ratings']);

            if ($q = trim($request->input('q', ''))) {
                $query->where('title', 'like', '%' . $q . '%');
            }

            $results = $query->orderBy('title', 'asc')->paginate(10);

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

    public function show(string $id)
    {
        try {
            $course = Course::withCount(['enrollments', 'ratings'])->find($id);

            if (!$course) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Course not found.',
                ], 404);
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

            $validator = Validations::validateCourse($request->all(), $id);

            if ($validator->fails()) {
                return response()->json([
                    'status'  => 422,
                    'message' => 'Validation failed.',
                    'errors'  => $validator->messages(),
                ], 422);
            }

            $data = $request->all();

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
}

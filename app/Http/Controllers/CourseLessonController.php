<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Helpers\Validations;
use App\Models\CourseLesson;
use App\Models\ScholarUser;

class CourseLessonController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = CourseLesson::withCount('resources');

            if ($q = trim($request->input('q', ''))) {
                $query->where('title', 'like', '%' . $q . '%');
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

        if (!$user || !in_array($user->role, ['instructor', 'admin'])) {
            return response()->json([
                'status'  => 403,
                'message' => 'Forbidden.',
            ], 403);
        }

        $validator = Validations::validateCourseLesson($request->all());

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => $validator->messages(),
            ], 422);
        }

        try {
            $data = $request->all();
            $courseLesson = CourseLesson::create($data);

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

        if (!$user || !in_array($user->role, ['instructor', 'admin'])) {
            return response()->json([
                'status'  => 403,
                'message' => 'Forbidden.',
            ], 403);
        }

        $validator = Validations::validateCourseLesson($request->all());

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

            $courseLesson->update($request->all());

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

        if (!$user || !in_array($user->role, ['instructor', 'admin'])) {
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

            $courseLesson->delete();

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
}

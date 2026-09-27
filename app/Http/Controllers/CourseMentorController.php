<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Helpers\Validations;
use App\Models\Course;
use App\Models\CourseMentor;
use App\Models\ScholarUser;

class CourseMentorController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = CourseMentor::query();

            if ($courseId = trim($request->input('course_id', ''))) {
                $query->where('course_id', $courseId);
            }

            $results = $query->orderBy('created_at', 'desc')->paginate(10);

            return response()->json([
                'status' => 200,
                'data'   => $results,
            ], 200);
        } catch (\Exception $e) {
            Log::error('CourseMentorController@index: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving course mentors.',
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $validator = Validations::validateCourseMentor($request->all());

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => $validator->messages(),
            ], 422);
        }

        try {
            $course = Course::find($request->input('course_id'));

            if (!$this->canManage($request, $course)) {
                return response()->json([
                    'status'  => 403,
                    'message' => 'Forbidden.',
                ], 403);
            }

            $data = $request->all();
            $courseMentor = CourseMentor::create($data);
            $courseMentor->refresh();

            return response()->json([
                'status'  => 201,
                'message' => 'Course mentor created successfully.',
                'data'    => $courseMentor,
            ], 201);
        } catch (\Exception $e) {
            Log::error('CourseMentorController@store: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while creating the course mentor.',
            ], 500);
        }
    }

    public function show(string $id)
    {
        try {
            $courseMentor = CourseMentor::find($id);

            if (!$courseMentor) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Course mentor not found.',
                ], 404);
            }

            return response()->json([
                'status' => 200,
                'data'   => $courseMentor,
            ], 200);
        } catch (\Exception $e) {
            Log::error('CourseMentorController@show: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving the course mentor.',
            ], 500);
        }
    }

    public function update(Request $request, string $id)
    {
        $validator = Validations::validateCourseMentor($request->all(), $id);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => $validator->messages(),
            ], 422);
        }

        try {
            $courseMentor = CourseMentor::find($id);

            if (!$courseMentor) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Course mentor not found.',
                ], 404);
            }

            if (!$this->canManage($request, $courseMentor->course)) {
                return response()->json([
                    'status'  => 403,
                    'message' => 'Forbidden.',
                ], 403);
            }

            $courseMentor->update($request->all());

            return response()->json([
                'status'  => 200,
                'message' => 'Course mentor updated successfully.',
                'data'    => $courseMentor,
            ], 200);
        } catch (\Exception $e) {
            Log::error('CourseMentorController@update: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while updating the course mentor.',
            ], 500);
        }
    }

    public function delete(Request $request, string $id)
    {
        try {
            $courseMentor = CourseMentor::find($id);

            if (!$courseMentor) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Course mentor not found.',
                ], 404);
            }

            if (!$this->canManage($request, $courseMentor->course)) {
                return response()->json([
                    'status'  => 403,
                    'message' => 'Forbidden.',
                ], 403);
            }

            $courseMentor->delete();

            return response()->json([
                'status'  => 200,
                'message' => 'Course mentor deleted successfully.',
            ], 200);
        } catch (\Exception $e) {
            Log::error('CourseMentorController@delete: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while deleting the course mentor.',
            ], 500);
        }
    }

    private function canManage(Request $request, ?Course $course): bool
    {
        if (!$course) {
            return false;
        }

        $user = ScholarUser::find($request->user()->id);

        if (!$user) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return $user->role === 'instructor' && $course->isManageableBy($request->user()->id);
    }
}

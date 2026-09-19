<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Helpers\Validations;
use App\Models\Course;
use App\Models\CourseModule;
use App\Models\ScholarUser;
use App\Traits\AuthorizesCourseOwnership;

class CourseModuleController extends Controller
{
    use AuthorizesCourseOwnership;

    public function index(Request $request)
    {
        try {
            $query = CourseModule::withCount('lessons')
                ->with(['quiz' => function ($quiz) {
                    $quiz->withCount('questions');
                }]);

            if ($request->boolean('with_lessons')) {
                $query->with(['lessons' => function ($lessons) {
                    $lessons->select(['id', 'module_id', 'title', 'duration_minutes', 'order_index'])
                        ->orderBy('order_index', 'asc');
                }]);
            }

            if ($q = trim($request->input('q', ''))) {
                $query->where('title', 'like', '%' . $q . '%');
            }

            if ($courseId = trim($request->input('course_id', ''))) {
                $query->where('course_id', $courseId);
            }

            $results = $query->orderBy('order_index', 'asc')->paginate(10);

            return response()->json([
                'status' => 200,
                'data'   => $results,
            ], 200);
        } catch (\Exception $e) {
            Log::error('CourseModuleController@index: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving course modules.',
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

        $validator = Validations::validateCourseModule($request->all());

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => $validator->messages(),
            ], 422);
        }

        $course = Course::find($request->input('course_id'));

        if (!$this->canManageCourse($request, $course)) {
            return response()->json([
                'status'  => 403,
                'message' => 'Forbidden.',
            ], 403);
        }

        try {
            $data = $request->all();
            $courseModule = CourseModule::create($data);
            $courseModule->refresh();

            return response()->json([
                'status'  => 201,
                'message' => 'Course module created successfully.',
                'data'    => $courseModule,
            ], 201);
        } catch (\Exception $e) {
            Log::error('CourseModuleController@store: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while creating the course module.',
            ], 500);
        }
    }

    public function show(string $id)
    {
        try {
            $courseModule = CourseModule::withCount('lessons')->find($id);

            if (!$courseModule) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Course module not found.',
                ], 404);
            }

            return response()->json([
                'status' => 200,
                'data'   => $courseModule,
            ], 200);
        } catch (\Exception $e) {
            Log::error('CourseModuleController@show: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving the course module.',
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

        $validator = Validations::validateCourseModule($request->all());

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => $validator->messages(),
            ], 422);
        }

        try {
            $courseModule = CourseModule::find($id);

            if (!$courseModule) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Course module not found.',
                ], 404);
            }

            $user = ScholarUser::find($request->user()->id);

            if (!$this->canManageCourse($request, $courseModule->course)) {
                return response()->json([
                    'status'  => 403,
                    'message' => 'Forbidden.',
                ], 403);
            }

            $data = $request->all();

            if ($user->role !== 'admin') {
                unset($data['course_id']);
            }

            $courseModule->update($data);

            return response()->json([
                'status'  => 200,
                'message' => 'Course module updated successfully.',
                'data'    => $courseModule,
            ], 200);
        } catch (\Exception $e) {
            Log::error('CourseModuleController@update: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while updating the course module.',
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
            $courseModule = CourseModule::find($id);

            if (!$courseModule) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Course module not found.',
                ], 404);
            }

            if (!$this->canManageCourse($request, $courseModule->course)) {
                return response()->json([
                    'status'  => 403,
                    'message' => 'Forbidden.',
                ], 403);
            }

            $courseModule->delete();

            return response()->json([
                'status'  => 200,
                'message' => 'Course module deleted successfully.',
            ], 200);
        } catch (\Exception $e) {
            Log::error('CourseModuleController@delete: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while deleting the course module.',
            ], 500);
        }
    }
}

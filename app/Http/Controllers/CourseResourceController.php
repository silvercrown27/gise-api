<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Helpers\Validations;
use App\Models\CourseResource;
use App\Models\ScholarUser;

class CourseResourceController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = CourseResource::query();

            if ($q = trim($request->input('q', ''))) {
                $query->where('title', 'like', '%' . $q . '%');
            }

            $results = $query->orderBy('created_at', 'desc')->paginate(10);

            return response()->json([
                'status' => 200,
                'data'   => $results,
            ], 200);
        } catch (\Exception $e) {
            Log::error('CourseResourceController@index: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving course resources.',
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

        $validator = Validations::validateCourseResource($request->all());

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => $validator->messages(),
            ], 422);
        }

        try {
            $data = $request->all();
            $courseResource = CourseResource::create($data);
            $courseResource->refresh();

            return response()->json([
                'status'  => 201,
                'message' => 'Course resource created successfully.',
                'data'    => $courseResource,
            ], 201);
        } catch (\Exception $e) {
            Log::error('CourseResourceController@store: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while creating the course resource.',
            ], 500);
        }
    }

    public function show(string $id)
    {
        try {
            $courseResource = CourseResource::find($id);

            if (!$courseResource) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Course resource not found.',
                ], 404);
            }

            return response()->json([
                'status' => 200,
                'data'   => $courseResource,
            ], 200);
        } catch (\Exception $e) {
            Log::error('CourseResourceController@show: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving the course resource.',
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

        $validator = Validations::validateCourseResource($request->all());

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => $validator->messages(),
            ], 422);
        }

        try {
            $courseResource = CourseResource::find($id);

            if (!$courseResource) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Course resource not found.',
                ], 404);
            }

            $courseResource->update($request->all());

            return response()->json([
                'status'  => 200,
                'message' => 'Course resource updated successfully.',
                'data'    => $courseResource,
            ], 200);
        } catch (\Exception $e) {
            Log::error('CourseResourceController@update: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while updating the course resource.',
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
            $courseResource = CourseResource::find($id);

            if (!$courseResource) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Course resource not found.',
                ], 404);
            }

            $courseResource->delete();

            return response()->json([
                'status'  => 200,
                'message' => 'Course resource deleted successfully.',
            ], 200);
        } catch (\Exception $e) {
            Log::error('CourseResourceController@delete: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while deleting the course resource.',
            ], 500);
        }
    }
}

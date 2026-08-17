<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Helpers\Validations;
use App\Models\CourseLead;
use App\Models\ScholarUser;

class CourseLeadController extends Controller
{
    public function index(Request $request)
    {
        try {
            $user = ScholarUser::find($request->user()->id);

            $query = CourseLead::query();

            if (!$user || $user->role === 'student') {
                $query->where('user_id', $request->user()->id);
            }

            if ($q = trim($request->input('q', ''))) {
                $query->where('full_name', 'like', '%' . $q . '%');
            }

            $results = $query->orderBy('created_at', 'desc')->paginate(10);

            return response()->json([
                'status' => 200,
                'data'   => $results,
            ], 200);
        } catch (\Exception $e) {
            Log::error('CourseLeadController@index: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving course leads.',
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $validator = Validations::validateCourseLead($request->all());

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => $validator->messages(),
            ], 422);
        }

        try {
            $data = $request->all();
            $courseLead = CourseLead::create($data);
            $courseLead->refresh();

            return response()->json([
                'status'  => 201,
                'message' => 'Course lead created successfully.',
                'data'    => $courseLead,
            ], 201);
        } catch (\Exception $e) {
            Log::error('CourseLeadController@store: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while creating the course lead.',
            ], 500);
        }
    }

    public function show(Request $request, string $id)
    {
        try {
            $user = ScholarUser::find($request->user()->id);

            if (!$user || $user->role === 'student') {
                return response()->json([
                    'status'  => 403,
                    'message' => 'Forbidden.',
                ], 403);
            }

            $courseLead = CourseLead::find($id);

            if (!$courseLead) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Course lead not found.',
                ], 404);
            }

            return response()->json([
                'status' => 200,
                'data'   => $courseLead,
            ], 200);
        } catch (\Exception $e) {
            Log::error('CourseLeadController@show: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving the course lead.',
            ], 500);
        }
    }

    public function update(Request $request, string $id)
    {
        $validator = Validations::validateCourseLead($request->all());

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => $validator->messages(),
            ], 422);
        }

        try {
            $user = ScholarUser::find($request->user()->id);

            if (!$user || $user->role === 'student') {
                return response()->json([
                    'status'  => 403,
                    'message' => 'Forbidden.',
                ], 403);
            }

            $courseLead = CourseLead::find($id);

            if (!$courseLead) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Course lead not found.',
                ], 404);
            }

            $courseLead->update($request->all());

            return response()->json([
                'status'  => 200,
                'message' => 'Course lead updated successfully.',
                'data'    => $courseLead,
            ], 200);
        } catch (\Exception $e) {
            Log::error('CourseLeadController@update: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while updating the course lead.',
            ], 500);
        }
    }

    public function delete(Request $request, string $id)
    {
        try {
            $user = ScholarUser::find($request->user()->id);

            if (!$user || $user->role === 'student') {
                return response()->json([
                    'status'  => 403,
                    'message' => 'Forbidden.',
                ], 403);
            }

            $courseLead = CourseLead::find($id);

            if (!$courseLead) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Course lead not found.',
                ], 404);
            }

            $courseLead->delete();

            return response()->json([
                'status'  => 200,
                'message' => 'Course lead deleted successfully.',
            ], 200);
        } catch (\Exception $e) {
            Log::error('CourseLeadController@delete: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while deleting the course lead.',
            ], 500);
        }
    }
}

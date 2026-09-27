<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Helpers\Validations;
use App\Models\CoursePricingHistory;
use App\Models\ScholarUser;

class CoursePricingHistoryController extends Controller
{
    public function index(Request $request)
    {
        try {
            $user = ScholarUser::find($request->user()->id);

            if (!$user || $user->role === 'student') {
                return response()->json([
                    'status'  => 403,
                    'message' => 'Forbidden.',
                ], 403);
            }

            $query = CoursePricingHistory::query();

            if ($user->role === 'instructor') {
                $query->whereHas('course', function ($q) use ($request) {
                    $q->manageableBy($request->user()->id);
                });
            }

            $results = $query->orderBy('created_at', 'desc')->paginate(10);

            return response()->json([
                'status' => 200,
                'data'   => $results,
            ], 200);
        } catch (\Exception $e) {
            Log::error('CoursePricingHistoryController@index: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving course pricing history.',
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $user = ScholarUser::find($request->user()->id);

        if (!$user || $user->role === 'student') {
            return response()->json([
                'status'  => 403,
                'message' => 'Forbidden.',
            ], 403);
        }

        $validator = Validations::validateCoursePricingHistory($request->all());

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => $validator->messages(),
            ], 422);
        }

        try {
            $data = $request->all();
            $coursePricingHistory = CoursePricingHistory::create($data);
            $coursePricingHistory->refresh();

            return response()->json([
                'status'  => 201,
                'message' => 'Course pricing history created successfully.',
                'data'    => $coursePricingHistory,
            ], 201);
        } catch (\Exception $e) {
            Log::error('CoursePricingHistoryController@store: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while creating the course pricing history.',
            ], 500);
        }
    }

    public function show(Request $request, string $id)
    {
        try {
            $user = ScholarUser::find($request->user()->id);
            $coursePricingHistory = CoursePricingHistory::find($id);

            if (!$coursePricingHistory) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Course pricing history not found.',
                ], 404);
            }

            $isAdmin = $user && $user->role === 'admin';
            $isOwningInstructor = $user && $user->role === 'instructor'
                && $coursePricingHistory->course
                && $coursePricingHistory->course->isManageableBy($request->user()->id);

            if (!$isAdmin && !$isOwningInstructor) {
                return response()->json([
                    'status'  => 403,
                    'message' => 'Forbidden.',
                ], 403);
            }

            return response()->json([
                'status' => 200,
                'data'   => $coursePricingHistory,
            ], 200);
        } catch (\Exception $e) {
            Log::error('CoursePricingHistoryController@show: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving the course pricing history.',
            ], 500);
        }
    }

    public function update(Request $request, string $id)
    {
        $user = ScholarUser::find($request->user()->id);

        if (!$user || $user->role === 'student') {
            return response()->json([
                'status'  => 403,
                'message' => 'Forbidden.',
            ], 403);
        }

        $validator = Validations::validateCoursePricingHistory($request->all());

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => $validator->messages(),
            ], 422);
        }

        try {
            $coursePricingHistory = CoursePricingHistory::find($id);

            if (!$coursePricingHistory) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Course pricing history not found.',
                ], 404);
            }

            $coursePricingHistory->update($request->all());

            return response()->json([
                'status'  => 200,
                'message' => 'Course pricing history updated successfully.',
                'data'    => $coursePricingHistory,
            ], 200);
        } catch (\Exception $e) {
            Log::error('CoursePricingHistoryController@update: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while updating the course pricing history.',
            ], 500);
        }
    }

    public function delete(Request $request, string $id)
    {
        $user = ScholarUser::find($request->user()->id);

        if (!$user || $user->role === 'student') {
            return response()->json([
                'status'  => 403,
                'message' => 'Forbidden.',
            ], 403);
        }

        try {
            $coursePricingHistory = CoursePricingHistory::find($id);

            if (!$coursePricingHistory) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Course pricing history not found.',
                ], 404);
            }

            $coursePricingHistory->delete();

            return response()->json([
                'status'  => 200,
                'message' => 'Course pricing history deleted successfully.',
            ], 200);
        } catch (\Exception $e) {
            Log::error('CoursePricingHistoryController@delete: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while deleting the course pricing history.',
            ], 500);
        }
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Helpers\Validations;
use App\Models\InstructorProfile;
use App\Models\ScholarUser;

class InstructorProfileController extends Controller
{
    public function index(Request $request)
    {
        try {
            $user = ScholarUser::find($request->user()->id);

            if (!$user || $user->role === 'learner') {
                return response()->json([
                    'status'  => 403,
                    'message' => 'Forbidden.',
                ], 403);
            }

            $query = InstructorProfile::query();

            if ($user->role === 'instructor') {
                $query->where('user_id', $request->user()->id);
            }

            $results = $query->orderBy('created_at', 'desc')->paginate(10);

            return response()->json([
                'status' => 200,
                'data'   => $results,
            ], 200);
        } catch (\Exception $e) {
            Log::error('InstructorProfileController@index: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving instructor profiles.',
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $user = ScholarUser::find($request->user()->id);

        if (!$user || $user->role === 'learner') {
            return response()->json([
                'status'  => 403,
                'message' => 'Forbidden.',
            ], 403);
        }

        $validator = Validations::validateInstructorProfile($request->all());

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => $validator->messages(),
            ], 422);
        }

        try {
            $data = $request->all();
            $instructorProfile = InstructorProfile::create($data);

            return response()->json([
                'status'  => 201,
                'message' => 'Instructor profile created successfully.',
                'data'    => $instructorProfile,
            ], 201);
        } catch (\Exception $e) {
            Log::error('InstructorProfileController@store: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while creating the instructor profile.',
            ], 500);
        }
    }

    public function show(Request $request, string $id)
    {
        try {
            $user = ScholarUser::find($request->user()->id);
            $instructorProfile = InstructorProfile::find($id);

            if (!$instructorProfile) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Instructor profile not found.',
                ], 404);
            }

            $isAdmin = $user && $user->role === 'admin';
            $isSelf = $instructorProfile->user_id === $request->user()->id;

            if (!$isAdmin && !$isSelf) {
                return response()->json([
                    'status'  => 403,
                    'message' => 'Forbidden.',
                ], 403);
            }

            return response()->json([
                'status' => 200,
                'data'   => $instructorProfile,
            ], 200);
        } catch (\Exception $e) {
            Log::error('InstructorProfileController@show: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving the instructor profile.',
            ], 500);
        }
    }

    public function update(Request $request, string $id)
    {
        $validator = Validations::validateInstructorProfile($request->all());

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => $validator->messages(),
            ], 422);
        }

        try {
            $user = ScholarUser::find($request->user()->id);
            $instructorProfile = InstructorProfile::find($id);

            if (!$instructorProfile) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Instructor profile not found.',
                ], 404);
            }

            $isAdmin = $user && $user->role === 'admin';
            $isSelf = $user && $user->role === 'instructor' && $instructorProfile->user_id === $request->user()->id;

            if (!$isAdmin && !$isSelf) {
                return response()->json([
                    'status'  => 403,
                    'message' => 'Forbidden.',
                ], 403);
            }

            $instructorProfile->update($request->all());

            return response()->json([
                'status'  => 200,
                'message' => 'Instructor profile updated successfully.',
                'data'    => $instructorProfile,
            ], 200);
        } catch (\Exception $e) {
            Log::error('InstructorProfileController@update: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while updating the instructor profile.',
            ], 500);
        }
    }

    public function delete(Request $request, string $id)
    {
        try {
            $user = ScholarUser::find($request->user()->id);

            if (!$user || $user->role !== 'admin') {
                return response()->json([
                    'status'  => 403,
                    'message' => 'Forbidden.',
                ], 403);
            }

            $instructorProfile = InstructorProfile::find($id);

            if (!$instructorProfile) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Instructor profile not found.',
                ], 404);
            }

            $instructorProfile->delete();

            return response()->json([
                'status'  => 200,
                'message' => 'Instructor profile deleted successfully.',
            ], 200);
        } catch (\Exception $e) {
            Log::error('InstructorProfileController@delete: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while deleting the instructor profile.',
            ], 500);
        }
    }
}

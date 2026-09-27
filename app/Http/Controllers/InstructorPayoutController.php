<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Helpers\Validations;
use App\Models\InstructorPayout;
use App\Models\ScholarUser;

class InstructorPayoutController extends Controller
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

            $query = InstructorPayout::query();

            if ($user->role === 'instructor') {
                $query->where('instructor_id', $request->user()->id);
            }

            $results = $query->orderBy('created_at', 'desc')->paginate(10);

            return response()->json([
                'status' => 200,
                'data'   => $results,
            ], 200);
        } catch (\Exception $e) {
            Log::error('InstructorPayoutController@index: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving instructor payouts.',
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

        $validator = Validations::validateInstructorPayout($request->all());

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => $validator->messages(),
            ], 422);
        }

        try {
            $data = $request->all();
            $instructorPayout = InstructorPayout::create($data);
            $instructorPayout->refresh();

            return response()->json([
                'status'  => 201,
                'message' => 'Instructor payout created successfully.',
                'data'    => $instructorPayout,
            ], 201);
        } catch (\Exception $e) {
            Log::error('InstructorPayoutController@store: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while creating the instructor payout.',
            ], 500);
        }
    }

    public function show(Request $request, string $id)
    {
        try {
            $user = ScholarUser::find($request->user()->id);
            $instructorPayout = InstructorPayout::find($id);

            if (!$instructorPayout) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Instructor payout not found.',
                ], 404);
            }

            $isAdmin = $user && $user->isAdmin();
            $isSelf = (string) $instructorPayout->instructor_id === (string) $request->user()->id;

            if (!$isAdmin && !$isSelf) {
                return response()->json([
                    'status'  => 403,
                    'message' => 'Forbidden.',
                ], 403);
            }

            return response()->json([
                'status' => 200,
                'data'   => $instructorPayout,
            ], 200);
        } catch (\Exception $e) {
            Log::error('InstructorPayoutController@show: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving the instructor payout.',
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

        $validator = Validations::validateInstructorPayout($request->all());

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => $validator->messages(),
            ], 422);
        }

        try {
            $instructorPayout = InstructorPayout::find($id);

            if (!$instructorPayout) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Instructor payout not found.',
                ], 404);
            }

            $instructorPayout->update($request->all());

            return response()->json([
                'status'  => 200,
                'message' => 'Instructor payout updated successfully.',
                'data'    => $instructorPayout,
            ], 200);
        } catch (\Exception $e) {
            Log::error('InstructorPayoutController@update: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while updating the instructor payout.',
            ], 500);
        }
    }

    public function delete(Request $request, string $id)
    {
        $user = ScholarUser::find($request->user()->id);

        if (!$user || !$user->isAdmin()) {
            return response()->json([
                'status'  => 403,
                'message' => 'Forbidden.',
            ], 403);
        }

        try {
            $instructorPayout = InstructorPayout::find($id);

            if (!$instructorPayout) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Instructor payout not found.',
                ], 404);
            }

            $instructorPayout->delete();

            return response()->json([
                'status'  => 200,
                'message' => 'Instructor payout deleted successfully.',
            ], 200);
        } catch (\Exception $e) {
            Log::error('InstructorPayoutController@delete: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while deleting the instructor payout.',
            ], 500);
        }
    }
}

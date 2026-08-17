<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Helpers\Validations;
use App\Models\Enrollment;
use App\Models\ScholarUser;

class EnrollmentController extends Controller
{
    public function index(Request $request)
    {
        try {
            $user = ScholarUser::find($request->user()->id);

            $query = Enrollment::query();

            if (!$user || $user->role === 'learner') {
                $query->where('learner_id', $request->user()->id);
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
            $data = $request->all();
            $enrollment = Enrollment::create($data);

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

    public function show(string $id)
    {
        try {
            $enrollment = Enrollment::find($id);

            if (!$enrollment) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Enrollment not found.',
                ], 404);
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
            $enrollment = Enrollment::find($id);

            if (!$enrollment) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Enrollment not found.',
                ], 404);
            }

            $enrollment->update($request->all());

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

    public function delete(string $id)
    {
        try {
            $enrollment = Enrollment::find($id);

            if (!$enrollment) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Enrollment not found.',
                ], 404);
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

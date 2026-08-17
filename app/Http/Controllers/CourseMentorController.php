<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Helpers\Validations;
use App\Models\CourseMentor;
use App\Models\ScholarUser;

class CourseMentorController extends Controller
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

            $query = CourseMentor::query();

            if ($user->role === 'instructor') {
                $query->where('mentor_id', $request->user()->id);
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
        $user = ScholarUser::find($request->user()->id);

        if (!$user || $user->role === 'learner') {
            return response()->json([
                'status'  => 403,
                'message' => 'Forbidden.',
            ], 403);
        }

        $validator = Validations::validateCourseMentor($request->all());

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => $validator->messages(),
            ], 422);
        }

        try {
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

    public function show(Request $request, string $id)
    {
        try {
            $user = ScholarUser::find($request->user()->id);
            $courseMentor = CourseMentor::find($id);

            if (!$courseMentor) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Course mentor not found.',
                ], 404);
            }

            $isAdmin = $user && $user->role === 'admin';
            $isSelf = (string) $courseMentor->mentor_id === (string) $request->user()->id;

            if (!$isAdmin && !$isSelf) {
                return response()->json([
                    'status'  => 403,
                    'message' => 'Forbidden.',
                ], 403);
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
        $user = ScholarUser::find($request->user()->id);

        if (!$user || $user->role === 'learner') {
            return response()->json([
                'status'  => 403,
                'message' => 'Forbidden.',
            ], 403);
        }

        $validator = Validations::validateCourseMentor($request->all());

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
        $user = ScholarUser::find($request->user()->id);

        if (!$user || $user->role === 'learner') {
            return response()->json([
                'status'  => 403,
                'message' => 'Forbidden.',
            ], 403);
        }

        try {
            $courseMentor = CourseMentor::find($id);

            if (!$courseMentor) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Course mentor not found.',
                ], 404);
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
}

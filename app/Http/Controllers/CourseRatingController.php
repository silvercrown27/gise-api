<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Helpers\Validations;
use App\Models\CourseRating;
use App\Models\ScholarUser;

class CourseRatingController extends Controller
{
    public function index(Request $request)
    {
        try {
            $user = ScholarUser::find($request->user()->id);

            $query = CourseRating::query();

            if (!$user || $user->role === 'student') {
                $query->where('learner_id', $request->user()->id);
            }

            $results = $query->orderBy('created_at', 'desc')->paginate(10);

            return response()->json([
                'status' => 200,
                'data'   => $results,
            ], 200);
        } catch (\Exception $e) {
            Log::error('CourseRatingController@index: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving course ratings.',
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $validator = Validations::validateCourseRating($request->all());

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => $validator->messages(),
            ], 422);
        }

        try {
            $user = ScholarUser::find($request->user()->id);
            $isElevated = $user && in_array($user->role, ['instructor', 'admin', 'super_admin']);

            $data = $request->all();

            if (!$isElevated) {
                $data['learner_id'] = $request->user()->id;
            }

            $courseRating = CourseRating::create($data);

            $courseRating->refresh();

            return response()->json([
                'status'  => 201,
                'message' => 'Course rating created successfully.',
                'data'    => $courseRating,
            ], 201);
        } catch (\Exception $e) {
            Log::error('CourseRatingController@store: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while creating the course rating.',
            ], 500);
        }
    }

    public function show(string $id)
    {
        try {
            $courseRating = CourseRating::find($id);

            if (!$courseRating) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Course rating not found.',
                ], 404);
            }

            return response()->json([
                'status' => 200,
                'data'   => $courseRating,
            ], 200);
        } catch (\Exception $e) {
            Log::error('CourseRatingController@show: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving the course rating.',
            ], 500);
        }
    }

    public function update(Request $request, string $id)
    {
        $validator = Validations::validateCourseRating($request->all());

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => $validator->messages(),
            ], 422);
        }

        try {
            $user = ScholarUser::find($request->user()->id);
            $courseRating = CourseRating::find($id);

            if (!$courseRating) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Course rating not found.',
                ], 404);
            }

            $isAdmin = $user && $user->isAdmin();
            $isOwner = (string) $courseRating->learner_id === (string) $request->user()->id;

            if (!$isAdmin && !$isOwner) {
                return response()->json([
                    'status'  => 403,
                    'message' => 'Forbidden.',
                ], 403);
            }

            $data = $request->all();

            if (!$isAdmin) {
                unset($data['learner_id']);
            }

            $courseRating->update($data);

            return response()->json([
                'status'  => 200,
                'message' => 'Course rating updated successfully.',
                'data'    => $courseRating,
            ], 200);
        } catch (\Exception $e) {
            Log::error('CourseRatingController@update: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while updating the course rating.',
            ], 500);
        }
    }

    public function delete(Request $request, string $id)
    {
        try {
            $user = ScholarUser::find($request->user()->id);
            $courseRating = CourseRating::find($id);

            if (!$courseRating) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Course rating not found.',
                ], 404);
            }

            $isAdmin = $user && $user->isAdmin();
            $isOwner = (string) $courseRating->learner_id === (string) $request->user()->id;

            if (!$isAdmin && !$isOwner) {
                return response()->json([
                    'status'  => 403,
                    'message' => 'Forbidden.',
                ], 403);
            }

            $courseRating->delete();

            return response()->json([
                'status'  => 200,
                'message' => 'Course rating deleted successfully.',
            ], 200);
        } catch (\Exception $e) {
            Log::error('CourseRatingController@delete: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while deleting the course rating.',
            ], 500);
        }
    }
}

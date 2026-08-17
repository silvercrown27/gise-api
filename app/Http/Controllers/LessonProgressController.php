<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Helpers\Validations;
use App\Models\Enrollment;
use App\Models\LessonProgress;
use App\Models\ScholarUser;

class LessonProgressController extends Controller
{
    public function index(Request $request)
    {
        try {
            $user = ScholarUser::find($request->user()->id);

            $query = LessonProgress::query();

            if (!$user || $user->role === 'student') {
                $query->whereHas('enrollment', function ($q) use ($request) {
                    $q->where('learner_id', $request->user()->id);
                });
            }

            $results = $query->orderBy('created_at', 'desc')->paginate(10);

            return response()->json([
                'status' => 200,
                'data'   => $results,
            ], 200);
        } catch (\Exception $e) {
            Log::error('LessonProgressController@index: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving lesson progress.',
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $validator = Validations::validateLessonProgress($request->all());

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => $validator->messages(),
            ], 422);
        }

        try {
            $user = ScholarUser::find($request->user()->id);
            $isElevated = $user && in_array($user->role, ['instructor', 'admin']);

            if (!$isElevated) {
                $enrollment = Enrollment::find($request->input('enrollment_id'));

                if (!$enrollment || (string) $enrollment->learner_id !== (string) $request->user()->id) {
                    return response()->json([
                        'status'  => 403,
                        'message' => 'Forbidden.',
                    ], 403);
                }
            }

            $data = $request->all();
            $lessonProgress = LessonProgress::create($data);
            $lessonProgress->refresh();

            return response()->json([
                'status'  => 201,
                'message' => 'Lesson progress created successfully.',
                'data'    => $lessonProgress,
            ], 201);
        } catch (\Exception $e) {
            Log::error('LessonProgressController@store: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while creating the lesson progress.',
            ], 500);
        }
    }

    public function show(Request $request, string $id)
    {
        try {
            $user = ScholarUser::find($request->user()->id);
            $lessonProgress = LessonProgress::find($id);

            if (!$lessonProgress) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Lesson progress not found.',
                ], 404);
            }

            $isElevated = $user && in_array($user->role, ['instructor', 'admin']);
            $isOwner = $lessonProgress->enrollment && (string) $lessonProgress->enrollment->learner_id === (string) $request->user()->id;

            if (!$isElevated && !$isOwner) {
                return response()->json([
                    'status'  => 403,
                    'message' => 'Forbidden.',
                ], 403);
            }

            return response()->json([
                'status' => 200,
                'data'   => $lessonProgress,
            ], 200);
        } catch (\Exception $e) {
            Log::error('LessonProgressController@show: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving the lesson progress.',
            ], 500);
        }
    }

    public function update(Request $request, string $id)
    {
        $validator = Validations::validateLessonProgress($request->all());

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => $validator->messages(),
            ], 422);
        }

        try {
            $user = ScholarUser::find($request->user()->id);
            $lessonProgress = LessonProgress::find($id);

            if (!$lessonProgress) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Lesson progress not found.',
                ], 404);
            }

            $isElevated = $user && in_array($user->role, ['instructor', 'admin']);
            $isOwner = $lessonProgress->enrollment && (string) $lessonProgress->enrollment->learner_id === (string) $request->user()->id;

            if (!$isElevated && !$isOwner) {
                return response()->json([
                    'status'  => 403,
                    'message' => 'Forbidden.',
                ], 403);
            }

            $lessonProgress->update($request->all());

            return response()->json([
                'status'  => 200,
                'message' => 'Lesson progress updated successfully.',
                'data'    => $lessonProgress,
            ], 200);
        } catch (\Exception $e) {
            Log::error('LessonProgressController@update: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while updating the lesson progress.',
            ], 500);
        }
    }

    public function delete(Request $request, string $id)
    {
        try {
            $user = ScholarUser::find($request->user()->id);

            if (!$user || !in_array($user->role, ['instructor', 'admin'])) {
                return response()->json([
                    'status'  => 403,
                    'message' => 'Forbidden.',
                ], 403);
            }

            $lessonProgress = LessonProgress::find($id);

            if (!$lessonProgress) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Lesson progress not found.',
                ], 404);
            }

            $lessonProgress->delete();

            return response()->json([
                'status'  => 200,
                'message' => 'Lesson progress deleted successfully.',
            ], 200);
        } catch (\Exception $e) {
            Log::error('LessonProgressController@delete: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while deleting the lesson progress.',
            ], 500);
        }
    }
}

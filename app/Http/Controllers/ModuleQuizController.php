<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Helpers\Validations;
use App\Models\ModuleQuiz;
use App\Models\ScholarUser;

class ModuleQuizController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = ModuleQuiz::withCount('questions');

            if ($moduleId = trim($request->input('module_id', ''))) {
                $query->where('module_id', $moduleId);
            }

            $results = $query->orderBy('created_at', 'desc')->paginate(10);

            return response()->json([
                'status' => 200,
                'data'   => $results,
            ], 200);
        } catch (\Exception $e) {
            Log::error('ModuleQuizController@index: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving module quizzes.',
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

        $validator = Validations::validateModuleQuiz($request->all());

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => $validator->messages(),
            ], 422);
        }

        try {
            $quiz = ModuleQuiz::create($request->all());
            $quiz->refresh();

            return response()->json([
                'status'  => 201,
                'message' => 'Module quiz created successfully.',
                'data'    => $quiz,
            ], 201);
        } catch (\Exception $e) {
            Log::error('ModuleQuizController@store: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while creating the module quiz.',
            ], 500);
        }
    }

    public function show(string $id)
    {
        try {
            $quiz = ModuleQuiz::with(['questions' => function ($q) {
                $q->orderBy('order_index', 'asc');
            }])->find($id);

            if (!$quiz) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Module quiz not found.',
                ], 404);
            }

            return response()->json([
                'status' => 200,
                'data'   => $quiz,
            ], 200);
        } catch (\Exception $e) {
            Log::error('ModuleQuizController@show: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving the module quiz.',
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

        $validator = Validations::validateModuleQuiz($request->all());

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => $validator->messages(),
            ], 422);
        }

        try {
            $quiz = ModuleQuiz::find($id);

            if (!$quiz) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Module quiz not found.',
                ], 404);
            }

            $quiz->update($request->all());

            return response()->json([
                'status'  => 200,
                'message' => 'Module quiz updated successfully.',
                'data'    => $quiz,
            ], 200);
        } catch (\Exception $e) {
            Log::error('ModuleQuizController@update: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while updating the module quiz.',
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
            $quiz = ModuleQuiz::find($id);

            if (!$quiz) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Module quiz not found.',
                ], 404);
            }

            $quiz->delete();

            return response()->json([
                'status'  => 200,
                'message' => 'Module quiz deleted successfully.',
            ], 200);
        } catch (\Exception $e) {
            Log::error('ModuleQuizController@delete: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while deleting the module quiz.',
            ], 500);
        }
    }
}

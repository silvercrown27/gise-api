<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Helpers\Validations;
use App\Models\ModuleQuizQuestion;
use App\Models\ScholarUser;

class ModuleQuizQuestionController extends Controller
{
    public function index(Request $request)
    {
        try {
            $user = $request->user() ? ScholarUser::find($request->user()->id) : null;
            $isAdminOrInstructor = $user && in_array($user->role, ['instructor', 'admin']);

            $query = ModuleQuizQuestion::query();

            if ($quizId = trim($request->input('quiz_id', ''))) {
                $query->where('quiz_id', $quizId);
            }

            $results = $query->orderBy('order_index', 'asc')->paginate(30);

            if (!$isAdminOrInstructor) {
                $results->getCollection()->makeHidden('correct_option_key');
            } else {
                $results->getCollection()->makeVisible('correct_option_key');
            }

            return response()->json([
                'status' => 200,
                'data'   => $results,
            ], 200);
        } catch (\Exception $e) {
            Log::error('ModuleQuizQuestionController@index: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving module quiz questions.',
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

        $validator = Validations::validateModuleQuizQuestion($request->all());

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => $validator->messages(),
            ], 422);
        }

        $data = $request->all();

        if (!array_key_exists($data['correct_option_key'], $data['options'])) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => ['correct_option_key' => ['Must match one of the provided option keys.']],
            ], 422);
        }

        try {
            $question = ModuleQuizQuestion::create($data);
            $question->refresh();
            $question->makeVisible('correct_option_key');

            return response()->json([
                'status'  => 201,
                'message' => 'Module quiz question created successfully.',
                'data'    => $question,
            ], 201);
        } catch (\Exception $e) {
            Log::error('ModuleQuizQuestionController@store: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while creating the module quiz question.',
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

        $validator = Validations::validateModuleQuizQuestion($request->all());

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => $validator->messages(),
            ], 422);
        }

        $data = $request->all();

        if (!array_key_exists($data['correct_option_key'], $data['options'])) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => ['correct_option_key' => ['Must match one of the provided option keys.']],
            ], 422);
        }

        try {
            $question = ModuleQuizQuestion::find($id);

            if (!$question) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Module quiz question not found.',
                ], 404);
            }

            $question->update($data);
            $question->makeVisible('correct_option_key');

            return response()->json([
                'status'  => 200,
                'message' => 'Module quiz question updated successfully.',
                'data'    => $question,
            ], 200);
        } catch (\Exception $e) {
            Log::error('ModuleQuizQuestionController@update: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while updating the module quiz question.',
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
            $question = ModuleQuizQuestion::find($id);

            if (!$question) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Module quiz question not found.',
                ], 404);
            }

            $question->delete();

            return response()->json([
                'status'  => 200,
                'message' => 'Module quiz question deleted successfully.',
            ], 200);
        } catch (\Exception $e) {
            Log::error('ModuleQuizQuestionController@delete: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while deleting the module quiz question.',
            ], 500);
        }
    }
}

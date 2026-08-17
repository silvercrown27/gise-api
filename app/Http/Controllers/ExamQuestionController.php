<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Helpers\Validations;
use App\Models\ExamQuestion;
use App\Models\ScholarUser;

class ExamQuestionController extends Controller
{
    public function index(Request $request)
    {
        try {
            $user = ScholarUser::find($request->user()->id);

            $query = ExamQuestion::query();

            if ($user && $user->role === 'instructor') {
                $query->whereHas('exam.course', function ($q) use ($request) {
                    $q->where('instructor_id', $request->user()->id);
                });
            }

            $results = $query->orderBy('order_index', 'asc')->paginate(10);

            if ($user && in_array($user->role, ['instructor', 'admin'])) {
                $results->getCollection()->transform(function ($question) {
                    return $question->makeVisible('correct_answer');
                });
            }

            return response()->json([
                'status' => 200,
                'data'   => $results,
            ], 200);
        } catch (\Exception $e) {
            Log::error('ExamQuestionController@index: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving exam questions.',
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

        $validator = Validations::validateExamQuestion($request->all());

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => $validator->messages(),
            ], 422);
        }

        try {
            $data = $request->all();
            $examQuestion = ExamQuestion::create($data);
            $examQuestion->refresh();

            return response()->json([
                'status'  => 201,
                'message' => 'Exam question created successfully.',
                'data'    => $examQuestion,
            ], 201);
        } catch (\Exception $e) {
            Log::error('ExamQuestionController@store: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while creating the exam question.',
            ], 500);
        }
    }

    public function show(Request $request, string $id)
    {
        try {
            $user = ScholarUser::find($request->user()->id);
            $examQuestion = ExamQuestion::find($id);

            if (!$examQuestion) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Exam question not found.',
                ], 404);
            }

            if ($user && in_array($user->role, ['instructor', 'admin'])) {
                $examQuestion->makeVisible('correct_answer');
            }

            return response()->json([
                'status' => 200,
                'data'   => $examQuestion,
            ], 200);
        } catch (\Exception $e) {
            Log::error('ExamQuestionController@show: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving the exam question.',
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

        $validator = Validations::validateExamQuestion($request->all());

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => $validator->messages(),
            ], 422);
        }

        try {
            $examQuestion = ExamQuestion::find($id);

            if (!$examQuestion) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Exam question not found.',
                ], 404);
            }

            $examQuestion->update($request->all());

            return response()->json([
                'status'  => 200,
                'message' => 'Exam question updated successfully.',
                'data'    => $examQuestion,
            ], 200);
        } catch (\Exception $e) {
            Log::error('ExamQuestionController@update: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while updating the exam question.',
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
            $examQuestion = ExamQuestion::find($id);

            if (!$examQuestion) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Exam question not found.',
                ], 404);
            }

            $examQuestion->delete();

            return response()->json([
                'status'  => 200,
                'message' => 'Exam question deleted successfully.',
            ], 200);
        } catch (\Exception $e) {
            Log::error('ExamQuestionController@delete: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while deleting the exam question.',
            ], 500);
        }
    }
}

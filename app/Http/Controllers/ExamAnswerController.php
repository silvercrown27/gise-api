<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Helpers\Validations;
use App\Models\ExamAnswer;
use App\Models\ScholarUser;

class ExamAnswerController extends Controller
{
    public function index(Request $request)
    {
        try {
            $user = ScholarUser::find($request->user()->id);

            $query = ExamAnswer::query();

            if (!$user || $user->role === 'student') {
                $query->whereHas('submission', function ($q) use ($request) {
                    $q->where('learner_id', $request->user()->id);
                });
            }

            $results = $query->orderBy('created_at', 'desc')->paginate(10);

            return response()->json([
                'status' => 200,
                'data'   => $results,
            ], 200);
        } catch (\Exception $e) {
            Log::error('ExamAnswerController@index: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving exam answers.',
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $validator = Validations::validateExamAnswer($request->all());

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

            $data = $request->all();

            if (!$isElevated) {
                unset($data['is_correct'], $data['marks_awarded']);
            }

            $examAnswer = ExamAnswer::create($data);

            $examAnswer->refresh();

            return response()->json([
                'status'  => 201,
                'message' => 'Exam answer created successfully.',
                'data'    => $examAnswer,
            ], 201);
        } catch (\Exception $e) {
            Log::error('ExamAnswerController@store: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while creating the exam answer.',
            ], 500);
        }
    }

    public function show(Request $request, string $id)
    {
        try {
            $user = ScholarUser::find($request->user()->id);
            $examAnswer = ExamAnswer::find($id);

            if (!$examAnswer) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Exam answer not found.',
                ], 404);
            }

            $isElevated = $user && in_array($user->role, ['instructor', 'admin']);
            $isOwner = $examAnswer->submission && (string) $examAnswer->submission->learner_id === (string) $request->user()->id;

            if (!$isElevated && !$isOwner) {
                return response()->json([
                    'status'  => 403,
                    'message' => 'Forbidden.',
                ], 403);
            }

            return response()->json([
                'status' => 200,
                'data'   => $examAnswer,
            ], 200);
        } catch (\Exception $e) {
            Log::error('ExamAnswerController@show: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving the exam answer.',
            ], 500);
        }
    }

    public function update(Request $request, string $id)
    {
        $validator = Validations::validateExamAnswer($request->all());

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => $validator->messages(),
            ], 422);
        }

        try {
            $user = ScholarUser::find($request->user()->id);
            $examAnswer = ExamAnswer::find($id);

            if (!$examAnswer) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Exam answer not found.',
                ], 404);
            }

            $isElevated = $user && in_array($user->role, ['instructor', 'admin']);
            $isOwner = $examAnswer->submission && (string) $examAnswer->submission->learner_id === (string) $request->user()->id;

            if (!$isElevated && !$isOwner) {
                return response()->json([
                    'status'  => 403,
                    'message' => 'Forbidden.',
                ], 403);
            }

            $data = $request->all();

            if (!$isElevated) {
                unset($data['is_correct'], $data['marks_awarded']);
            }

            $examAnswer->update($data);

            return response()->json([
                'status'  => 200,
                'message' => 'Exam answer updated successfully.',
                'data'    => $examAnswer,
            ], 200);
        } catch (\Exception $e) {
            Log::error('ExamAnswerController@update: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while updating the exam answer.',
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

            $examAnswer = ExamAnswer::find($id);

            if (!$examAnswer) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Exam answer not found.',
                ], 404);
            }

            $examAnswer->delete();

            return response()->json([
                'status'  => 200,
                'message' => 'Exam answer deleted successfully.',
            ], 200);
        } catch (\Exception $e) {
            Log::error('ExamAnswerController@delete: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while deleting the exam answer.',
            ], 500);
        }
    }
}

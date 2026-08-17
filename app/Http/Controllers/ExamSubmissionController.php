<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Helpers\Validations;
use App\Models\ExamSubmission;
use App\Models\ScholarUser;

class ExamSubmissionController extends Controller
{
    public function index(Request $request)
    {
        try {
            $user = ScholarUser::find($request->user()->id);

            $query = ExamSubmission::query();

            if (!$user || $user->role === 'learner') {
                $query->where('learner_id', $request->user()->id);
            }

            $results = $query->orderBy('created_at', 'desc')->paginate(10);

            return response()->json([
                'status' => 200,
                'data'   => $results,
            ], 200);
        } catch (\Exception $e) {
            Log::error('ExamSubmissionController@index: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving exam submissions.',
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $validator = Validations::validateExamSubmission($request->all());

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
                unset($data['score'], $data['status']);
                $data['learner_id'] = $request->user()->id;
            }

            $examSubmission = ExamSubmission::create($data);

            $examSubmission->refresh();

            return response()->json([
                'status'  => 201,
                'message' => 'Exam submission created successfully.',
                'data'    => $examSubmission,
            ], 201);
        } catch (\Exception $e) {
            Log::error('ExamSubmissionController@store: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while creating the exam submission.',
            ], 500);
        }
    }

    public function show(Request $request, string $id)
    {
        try {
            $user = ScholarUser::find($request->user()->id);
            $examSubmission = ExamSubmission::find($id);

            if (!$examSubmission) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Exam submission not found.',
                ], 404);
            }

            $isElevated = $user && in_array($user->role, ['instructor', 'admin']);
            $isOwner = (string) $examSubmission->learner_id === (string) $request->user()->id;

            if (!$isElevated && !$isOwner) {
                return response()->json([
                    'status'  => 403,
                    'message' => 'Forbidden.',
                ], 403);
            }

            return response()->json([
                'status' => 200,
                'data'   => $examSubmission,
            ], 200);
        } catch (\Exception $e) {
            Log::error('ExamSubmissionController@show: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving the exam submission.',
            ], 500);
        }
    }

    public function update(Request $request, string $id)
    {
        $validator = Validations::validateExamSubmission($request->all());

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => $validator->messages(),
            ], 422);
        }

        try {
            $user = ScholarUser::find($request->user()->id);
            $examSubmission = ExamSubmission::find($id);

            if (!$examSubmission) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Exam submission not found.',
                ], 404);
            }

            $isElevated = $user && in_array($user->role, ['instructor', 'admin']);
            $isOwner = (string) $examSubmission->learner_id === (string) $request->user()->id;

            if (!$isElevated && !$isOwner) {
                return response()->json([
                    'status'  => 403,
                    'message' => 'Forbidden.',
                ], 403);
            }

            $data = $request->all();

            if (!$isElevated) {
                unset($data['score'], $data['status'], $data['learner_id']);
            }

            $examSubmission->update($data);

            return response()->json([
                'status'  => 200,
                'message' => 'Exam submission updated successfully.',
                'data'    => $examSubmission,
            ], 200);
        } catch (\Exception $e) {
            Log::error('ExamSubmissionController@update: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while updating the exam submission.',
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

            $examSubmission = ExamSubmission::find($id);

            if (!$examSubmission) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Exam submission not found.',
                ], 404);
            }

            $examSubmission->delete();

            return response()->json([
                'status'  => 200,
                'message' => 'Exam submission deleted successfully.',
            ], 200);
        } catch (\Exception $e) {
            Log::error('ExamSubmissionController@delete: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while deleting the exam submission.',
            ], 500);
        }
    }
}

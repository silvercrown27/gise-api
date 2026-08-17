<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Helpers\Validations;
use App\Models\Exam;
use App\Models\ScholarUser;

class ExamController extends Controller
{
    public function index(Request $request)
    {
        try {
            $user = ScholarUser::find($request->user()->id);

            $query = Exam::withCount('questions');

            if ($user && $user->role === 'instructor') {
                $query->whereHas('course', function ($q) use ($request) {
                    $q->where('instructor_id', $request->user()->id);
                });
            } elseif (!$user || $user->role !== 'admin') {
                $query->where('id', null);
            }

            if ($q = trim($request->input('q', ''))) {
                $query->where('title', 'like', '%' . $q . '%');
            }

            $results = $query->orderBy('created_at', 'desc')->paginate(10);

            return response()->json([
                'status' => 200,
                'data'   => $results,
            ], 200);
        } catch (\Exception $e) {
            Log::error('ExamController@index: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving exams.',
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

        $validator = Validations::validateExam($request->all());

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => $validator->messages(),
            ], 422);
        }

        try {
            $data = $request->all();
            $exam = Exam::create($data);
            $exam->refresh();

            return response()->json([
                'status'  => 201,
                'message' => 'Exam created successfully.',
                'data'    => $exam,
            ], 201);
        } catch (\Exception $e) {
            Log::error('ExamController@store: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while creating the exam.',
            ], 500);
        }
    }

    public function show(Request $request, string $id)
    {
        try {
            $user = ScholarUser::find($request->user()->id);
            $exam = Exam::withCount('questions')->find($id);

            if (!$exam) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Exam not found.',
                ], 404);
            }

            $isAdmin = $user && $user->role === 'admin';
            $isOwningInstructor = $user && $user->role === 'instructor'
                && $exam->course
                && $exam->course->instructor_id === $request->user()->id;

            if (!$isAdmin && !$isOwningInstructor && (!$user || $user->role !== 'student')) {
                return response()->json([
                    'status'  => 403,
                    'message' => 'Forbidden.',
                ], 403);
            }

            return response()->json([
                'status' => 200,
                'data'   => $exam,
            ], 200);
        } catch (\Exception $e) {
            Log::error('ExamController@show: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving the exam.',
            ], 500);
        }
    }

    public function update(Request $request, string $id)
    {
        try {
            $exam = Exam::find($id);

            if (!$exam) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Exam not found.',
                ], 404);
            }

            $user = ScholarUser::find($request->user()->id);

            $isAdmin = $user && $user->role === 'admin';
            $isOwningInstructor = $user && $user->role === 'instructor'
                && $exam->course
                && $exam->course->instructor_id === $request->user()->id;

            if (!$isAdmin && !$isOwningInstructor) {
                return response()->json([
                    'status'  => 403,
                    'message' => 'Forbidden.',
                ], 403);
            }

            $validator = Validations::validateExam($request->all());

            if ($validator->fails()) {
                return response()->json([
                    'status'  => 422,
                    'message' => 'Validation failed.',
                    'errors'  => $validator->messages(),
                ], 422);
            }

            $exam->update($request->all());

            return response()->json([
                'status'  => 200,
                'message' => 'Exam updated successfully.',
                'data'    => $exam,
            ], 200);
        } catch (\Exception $e) {
            Log::error('ExamController@update: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while updating the exam.',
            ], 500);
        }
    }

    public function delete(Request $request, string $id)
    {
        try {
            $exam = Exam::find($id);

            if (!$exam) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Exam not found.',
                ], 404);
            }

            $user = ScholarUser::find($request->user()->id);

            $isAdmin = $user && $user->role === 'admin';
            $isOwningInstructor = $user && $user->role === 'instructor'
                && $exam->course
                && $exam->course->instructor_id === $request->user()->id;

            if (!$isAdmin && !$isOwningInstructor) {
                return response()->json([
                    'status'  => 403,
                    'message' => 'Forbidden.',
                ], 403);
            }

            $exam->delete();

            return response()->json([
                'status'  => 200,
                'message' => 'Exam deleted successfully.',
            ], 200);
        } catch (\Exception $e) {
            Log::error('ExamController@delete: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while deleting the exam.',
            ], 500);
        }
    }
}

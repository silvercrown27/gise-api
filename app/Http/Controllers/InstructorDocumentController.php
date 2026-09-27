<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\Controller;
use App\Helpers\Utilities;
use App\Helpers\Validations;
use App\Models\InstructorDocument;
use App\Models\ScholarUser;

class InstructorDocumentController extends Controller
{
    public function index(Request $request)
    {
        try {
            $user = ScholarUser::find($request->user()->id);

            if (!$user || $user->role === 'student') {
                return response()->json([
                    'status'  => 403,
                    'message' => 'Forbidden.',
                ], 403);
            }

            $query = InstructorDocument::query();

            if ($user->role === 'instructor') {
                $query->where('instructor_id', $request->user()->id);
            } elseif ($instructorId = trim($request->input('instructor_id', ''))) {
                $query->where('instructor_id', $instructorId);
            }

            $results = $query->orderBy('created_at', 'desc')->paginate(10);

            return response()->json([
                'status' => 200,
                'data'   => $results,
            ], 200);
        } catch (\Exception $e) {
            Log::error('InstructorDocumentController@index: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving instructor documents.',
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $user = ScholarUser::find($request->user()->id);

        if (!$user || $user->role !== 'instructor') {
            return response()->json([
                'status'  => 403,
                'message' => 'Forbidden.',
            ], 403);
        }

        $data = $request->all();
        $data['instructor_id'] = $request->user()->id;

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $upload = Utilities::uploadFile($file, 'instructor-documents/' . $request->user()->id);

            if ($upload['status'] !== 200) {
                return response()->json([
                    'status'  => 500,
                    'message' => $upload['message'],
                ], 500);
            }

            $data['file_url'] = Storage::url($upload['path']);
            $data['file_type'] = $file->getClientOriginalExtension();
        }

        $validator = Validations::validateInstructorDocument($data);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => $validator->messages(),
            ], 422);
        }

        try {
            $document = InstructorDocument::create($data);
            $document->refresh();

            return response()->json([
                'status'  => 201,
                'message' => 'Instructor document uploaded successfully.',
                'data'    => $document,
            ], 201);
        } catch (\Exception $e) {
            Log::error('InstructorDocumentController@store: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while uploading the document.',
            ], 500);
        }
    }

    public function show(Request $request, string $id)
    {
        try {
            $user = ScholarUser::find($request->user()->id);
            $document = InstructorDocument::find($id);

            if (!$document) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Instructor document not found.',
                ], 404);
            }

            $isAdmin = $user && $user->isAdmin();
            $isOwner = (string) $document->instructor_id === (string) $request->user()->id;

            if (!$isAdmin && !$isOwner) {
                return response()->json([
                    'status'  => 403,
                    'message' => 'Forbidden.',
                ], 403);
            }

            return response()->json([
                'status' => 200,
                'data'   => $document,
            ], 200);
        } catch (\Exception $e) {
            Log::error('InstructorDocumentController@show: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving the document.',
            ], 500);
        }
    }

    public function delete(Request $request, string $id)
    {
        try {
            $user = ScholarUser::find($request->user()->id);
            $document = InstructorDocument::find($id);

            if (!$document) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Instructor document not found.',
                ], 404);
            }

            $isAdmin = $user && $user->isAdmin();
            $isOwner = (string) $document->instructor_id === (string) $request->user()->id;

            if (!$isAdmin && !$isOwner) {
                return response()->json([
                    'status'  => 403,
                    'message' => 'Forbidden.',
                ], 403);
            }

            $document->delete();

            return response()->json([
                'status'  => 200,
                'message' => 'Instructor document deleted successfully.',
            ], 200);
        } catch (\Exception $e) {
            Log::error('InstructorDocumentController@delete: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while deleting the document.',
            ], 500);
        }
    }
}

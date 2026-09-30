<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\Controller;
use App\Helpers\Validations;
use App\Models\InstructorDocument;
use App\Models\InstructorProfile;
use App\Models\ScholarUser;
use App\Services\NotificationService;
use Illuminate\Support\Str;

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

            $perPage = min(max((int) $request->input('per_page', 10), 1), 50);
            $results = $query->orderBy('created_at', 'desc')->paginate($perPage);

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

        // Only these fields are read from the request - never a client-supplied
        // path or URL - and the file is validated by its contents before it is stored.
        $input = $request->only(['document_type', 'title']) + ['file' => $request->file('file')];
        $validator = Validations::validateInstructorDocument($input);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->messages(),
            ], 422);
        }

        $instructorId = (string) $request->user()->id;
        $type = $input['document_type'];

        $existing = InstructorDocument::where('instructor_id', $instructorId)->get();

        if ($existing->count() >= InstructorDocument::MAX_DOCUMENTS) {
            return response()->json([
                'status'  => 422,
                'message' => 'You have reached the limit of ' . InstructorDocument::MAX_DOCUMENTS . ' documents. Remove one before adding another.',
                'errors'  => ['file' => ['Document limit reached.']],
            ], 422);
        }

        if (isset(InstructorDocument::REQUIRED_TYPES[$type])
            && $existing->where('document_type', $type)->count() >= InstructorDocument::MAX_PER_REQUIRED_TYPE) {
            return response()->json([
                'status'  => 422,
                'message' => 'You already have ' . InstructorDocument::MAX_PER_REQUIRED_TYPE . ' files for "' . InstructorDocument::REQUIRED_TYPES[$type] . '". Remove one before adding another.',
                'errors'  => ['file' => ['Too many files of this type.']],
            ], 422);
        }

        try {
            $file = $request->file('file');
            $extension = strtolower($file->extension() ?: $file->getClientOriginalExtension());
            $path = Storage::disk('local')->putFileAs(
                "instructor-documents/{$instructorId}",
                $file,
                Str::uuid() . '.' . $extension
            );

            if (!$path) {
                throw new \RuntimeException('The file could not be stored.');
            }

            $missingBefore = $this->missingRequiredTypes($existing);

            $document = InstructorDocument::create([
                'instructor_id' => $instructorId,
                'document_type' => $type,
                'title' => $input['title'],
                'path' => $path,
                'original_name' => Str::limit(basename(str_replace('\\', '/', $file->getClientOriginalName())), 200, ''),
                'mime_type' => $file->getMimeType(),
                'size_bytes' => $file->getSize(),
                'file_type' => $extension,
            ]);
            $document->refresh();

            // Tell staff the moment a mentor's set is complete, so it doesn't wait unnoticed.
            if ($missingBefore && !$this->missingRequiredTypes($existing->push($document))) {
                $this->notifyReadyForReview($request, $instructorId);
            }

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

            // Removing a document removes the file too - it isn't kept around.
            if ($document->path) {
                Storage::disk('local')->delete($document->path);
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

    /**
     * The file itself. Only its owner or an admin may read it; anyone else is
     * told it doesn't exist. PDFs and images open in the browser, Word files download.
     */
    public function download(Request $request, string $id)
    {
        $user = ScholarUser::find($request->user()->id);
        $document = InstructorDocument::find($id);

        $allowed = $document && ($user?->isAdmin() || (string) $document->instructor_id === (string) $request->user()->id);
        $disk = Storage::disk('local');

        if (!$allowed || !$document->path || !$disk->exists($document->path)) {
            return response()->json(['status' => 404, 'message' => 'Document not found.'], 404);
        }

        $inline = in_array($document->file_type, ['pdf', 'jpg', 'jpeg', 'png'], true);

        return $disk->response(
            $document->path,
            $document->original_name ?: basename($document->path),
            [
                'Content-Type' => $document->mime_type ?: 'application/octet-stream',
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store',
            ],
            $inline ? 'inline' : 'attachment'
        );
    }

    /** @return array<string,string> required document types not yet uploaded */
    private function missingRequiredTypes($documents): array
    {
        return array_diff_key(InstructorDocument::REQUIRED_TYPES, $documents->pluck('document_type')->filter()->flip()->all());
    }

    private function notifyReadyForReview(Request $request, string $instructorId): void
    {
        $profileId = InstructorProfile::where('user_id', $instructorId)->value('id');

        NotificationService::notifyAdmins(
            'system',
            "{$request->user()->name} has uploaded all required verification documents and is ready for review.",
            $profileId ? "/admin/instructors/{$profileId}" : '/admin/instructors'
        );
    }
}

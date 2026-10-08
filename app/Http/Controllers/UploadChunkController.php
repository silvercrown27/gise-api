<?php

namespace App\Http\Controllers;

use App\Services\ChunkedUploads;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

/**
 * Receives one piece of a large file. The finished upload is then attached to
 * the real request (course material, document...) by upload_id - see
 * ResolveChunkedUpload.
 */
class UploadChunkController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'upload_id' => 'required|uuid',
            'index'     => 'required|integer|min:0|max:' . (ChunkedUploads::MAX_CHUNKS - 1),
            'total'     => 'required|integer|min:1|max:' . ChunkedUploads::MAX_CHUNKS . '|gte:index',
            'chunk'     => 'required|file|max:' . ChunkedUploads::MAX_CHUNK_KB,
        ], [
            'chunk.max'      => 'That piece of the file is too large.',
            'chunk.uploaded' => 'A piece of the file did not arrive. Please try again.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->messages(),
            ], 422);
        }

        if ((int) $request->input('index') >= (int) $request->input('total')) {
            return response()->json(['status' => 422, 'message' => 'Chunk index is out of range.'], 422);
        }

        try {
            $received = ChunkedUploads::storeChunk(
                (string) $request->user()->id,
                $request->input('upload_id'),
                (int) $request->input('index'),
                $request->file('chunk'),
            );
        } catch (RuntimeException $e) {
            return response()->json(['status' => 413, 'message' => $e->getMessage()], 413);
        } catch (\Exception $e) {
            Log::error('UploadChunkController@store: ' . $e->getMessage());
            return response()->json(['status' => 500, 'message' => 'The piece could not be saved. Please try again.'], 500);
        }

        return response()->json(['status' => 200, 'received' => $received], 200);
    }

    /** Abort: drop whatever was uploaded so far. */
    public function destroy(Request $request, string $uploadId)
    {
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $uploadId)) {
            return response()->json(['status' => 404, 'message' => 'Upload not found.'], 404);
        }

        ChunkedUploads::discard((string) $request->user()->id, $uploadId);

        return response()->json(['status' => 200, 'message' => 'Upload cancelled.'], 200);
    }
}

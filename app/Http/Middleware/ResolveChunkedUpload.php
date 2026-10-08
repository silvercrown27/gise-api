<?php

namespace App\Http\Middleware;

use App\Services\ChunkedUploads;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets an upload endpoint accept a file that was sent earlier in chunks.
 *
 * When the request carries upload_id (and no file), the chunks are stitched
 * together and placed on the request as the named file field, so the
 * controller and its validation (size, type, content) run exactly as they do
 * for a normal upload.
 *
 * Usage: ->middleware('chunked:file')  or  ->middleware('chunked:content_file')
 */
class ResolveChunkedUpload
{
    public function handle(Request $request, Closure $next, string $field = 'file'): Response
    {
        if (!$request->filled('upload_id') || $request->files->has($field)) {
            return $next($request);
        }

        $user = $request->user();
        if (!$user) {
            return response()->json(['status' => 401, 'message' => 'Unauthenticated.'], 401);
        }

        $validator = Validator::make($request->all(), [
            'upload_id'    => 'required|uuid',
            'upload_total' => 'required|integer|min:1|max:' . ChunkedUploads::MAX_CHUNKS,
            'upload_name'  => 'required|string|max:255',
            'upload_size'  => 'required|integer|min:1|max:' . ChunkedUploads::MAX_TOTAL_BYTES,
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->messages(),
            ], 422);
        }

        try {
            $file = ChunkedUploads::assemble(
                (string) $user->id,
                $request->input('upload_id'),
                (int) $request->input('upload_total'),
                $request->input('upload_name'),
                (int) $request->input('upload_size'),
            );
        } catch (RuntimeException $e) {
            return response()->json([
                'status'  => 422,
                'message' => $e->getMessage(),
                'errors'  => [$field => [$e->getMessage()]],
            ], 422);
        }

        $request->files->set($field, $file);

        // Laravel memoises the converted file list the first time anything reads
        // it (the validator above did), so drop the memo or the new file is invisible.
        (fn () => $this->convertedFiles = null)->call($request);

        return $next($request);
    }
}

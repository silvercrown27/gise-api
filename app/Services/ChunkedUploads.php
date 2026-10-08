<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Large files arrive as small numbered chunks (so each request is short and a
 * failed chunk can be retried alone), are parked on the private disk under
 * the uploader's own id, then stitched back into one file that the normal
 * upload validation and storage code treats like any other upload.
 */
class ChunkedUploads
{
    /** Largest single chunk, in KB. The browser sends 5 MB pieces. */
    public const MAX_CHUNK_KB = 6144;

    public const MAX_CHUNKS = 30;

    /** Hard ceiling for one assembled upload; matches the 100 MB nginx/Cloudflare limit. */
    public const MAX_TOTAL_BYTES = 100 * 1024 * 1024;

    private const DISK = 'local';

    private static function dir(string $userId, string $uploadId): string
    {
        // Both parts are validated (auth id, uuid) before they get here.
        return "chunks/{$userId}/{$uploadId}";
    }

    public static function disk()
    {
        return Storage::disk(self::DISK);
    }

    public static function storeChunk(string $userId, string $uploadId, int $index, UploadedFile $chunk): int
    {
        $dir = self::dir($userId, $uploadId);
        self::disk()->putFileAs($dir, $chunk, sprintf('%05d.part', $index));

        $bytes = 0;
        $count = 0;
        foreach (self::disk()->files($dir) as $part) {
            if (str_ends_with($part, '.part')) {
                $bytes += self::disk()->size($part);
                $count++;
            }
        }

        if ($bytes > self::MAX_TOTAL_BYTES) {
            self::discard($userId, $uploadId);
            throw new RuntimeException('The upload is larger than the 100 MB limit.');
        }

        return $count;
    }

    public static function discard(string $userId, string $uploadId): void
    {
        self::disk()->deleteDirectory(self::dir($userId, $uploadId));
    }

    /**
     * Join the chunks into one file and return it as an uploaded file. The
     * chunks are removed once the request finishes.
     *
     * @throws RuntimeException when chunks are missing or the size is wrong
     */
    public static function assemble(string $userId, string $uploadId, int $total, string $name, int $expectedSize): UploadedFile
    {
        $dir = self::dir($userId, $uploadId);
        $disk = self::disk();

        $missing = [];
        for ($i = 0; $i < $total; $i++) {
            if (!$disk->exists($dir . '/' . sprintf('%05d.part', $i))) {
                $missing[] = $i;
            }
        }
        if ($missing) {
            throw new RuntimeException('The upload is incomplete (' . count($missing) . ' of ' . $total . ' parts missing). Please try again.');
        }

        $target = $disk->path($dir . '/assembled');
        $out = fopen($target, 'wb');
        if (!$out) {
            throw new RuntimeException('The upload could not be assembled.');
        }

        try {
            for ($i = 0; $i < $total; $i++) {
                $in = fopen($disk->path($dir . '/' . sprintf('%05d.part', $i)), 'rb');
                stream_copy_to_stream($in, $out);
                fclose($in);
            }
        } finally {
            fclose($out);
        }

        if (filesize($target) !== $expectedSize) {
            self::discard($userId, $uploadId);
            throw new RuntimeException('The upload arrived damaged (size mismatch). Please try again.');
        }

        // Clean up after the response is sent; storing the file has copied it by then.
        app()->terminating(fn () => self::discard($userId, $uploadId));

        // test: true skips the is_uploaded_file() check, which a stitched file can never pass.
        return new UploadedFile($target, basename(str_replace('\\', '/', $name)), null, UPLOAD_ERR_OK, true);
    }

    /** Remove chunk folders nobody finished (abandoned or failed uploads). */
    public static function prune(int $olderThanHours = 24): int
    {
        $removed = 0;
        $cutoff = now()->subHours($olderThanHours)->getTimestamp();

        foreach (self::disk()->directories('chunks') as $userDir) {
            foreach (self::disk()->directories($userDir) as $uploadDir) {
                $newest = max(array_map(fn ($f) => self::disk()->lastModified($f), self::disk()->files($uploadDir)) ?: [0]);
                if ($newest < $cutoff) {
                    self::disk()->deleteDirectory($uploadDir);
                    $removed++;
                }
            }
        }

        return $removed;
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Identity documents, CVs and certificates must not be readable by anyone
 * with a link. They move from the public disk (served at /storage) to the
 * private local disk, and are only handed out by an authorised download
 * endpoint (the owner, or an admin).
 *
 * Existing files are moved as part of this migration; a file that can't be
 * found is left as it was (its row simply has no path and can't be
 * downloaded, so the mentor is asked to upload it again).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instructor_documents', function (Blueprint $table) {
            if (!Schema::hasColumn('instructor_documents', 'path')) {
                $table->string('path', 500)->nullable()->after('title');
            }
            if (!Schema::hasColumn('instructor_documents', 'original_name')) {
                $table->string('original_name')->nullable()->after('path');
            }
            if (!Schema::hasColumn('instructor_documents', 'mime_type')) {
                $table->string('mime_type', 120)->nullable()->after('original_name');
            }
            if (!Schema::hasColumn('instructor_documents', 'size_bytes')) {
                $table->unsignedBigInteger('size_bytes')->nullable()->after('mime_type');
            }
            // Legacy public URL; new uploads have none.
            $table->string('file_url')->nullable()->change();
        });

        $public = Storage::disk('public');
        $private = Storage::disk('local');

        foreach (DB::table('instructor_documents')->whereNull('path')->whereNotNull('file_url')->get() as $document) {
            $relative = ltrim((string) preg_replace('#^.*?/storage/#', '', $document->file_url), '/');

            if ($relative === '' || !$public->exists($relative)) {
                continue;
            }

            $private->writeStream($relative, $public->readStream($relative));

            DB::table('instructor_documents')->where('id', $document->id)->update([
                'path' => $relative,
                'original_name' => basename($relative),
                'mime_type' => $public->mimeType($relative) ?: null,
                'size_bytes' => $public->size($relative),
                'file_url' => null,
            ]);

            // Only remove the public copy once the private one is in place.
            if ($private->exists($relative)) {
                $public->delete($relative);
            }
        }
    }

    public function down(): void
    {
        // Files stay private: moving identity documents back to a public folder would undo the fix.
        Schema::table('instructor_documents', function (Blueprint $table) {
            $columns = array_values(array_filter(
                ['path', 'original_name', 'mime_type', 'size_bytes'],
                fn ($column) => Schema::hasColumn('instructor_documents', $column)
            ));
            if ($columns) {
                $table->dropColumn($columns);
            }
        });
    }
};

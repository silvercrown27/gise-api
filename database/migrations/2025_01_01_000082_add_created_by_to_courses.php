<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who created a course. Every course is filed under the platform owner (instructor_id), so that
 * field can't say who actually made it. Courses created before this column existed stay empty:
 * nothing recorded their author.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('courses', 'created_by')) {
            Schema::table('courses', function (Blueprint $table) {
                $table->uuid('created_by')->nullable()->after('instructor_id')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('courses', 'created_by')) {
            Schema::table('courses', function (Blueprint $table) {
                $table->dropIndex(['created_by']);
                $table->dropColumn('created_by');
            });
        }
    }
};

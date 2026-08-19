<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $isMysql = DB::getDriverName() === 'mysql';

        if ($isMysql) {
            // In the real database, course_id's own FK ended up relying on the
            // compound (course_id, mentor_id) unique index rather than a
            // dedicated single-column index, so it must be dropped and
            // recreated around the column changes below (MySQL requires an
            // index on the leftmost FK column at all times).
            Schema::table('course_mentors', function (Blueprint $table) {
                $table->dropForeign(['course_id']);
            });
        }

        Schema::table('course_mentors', function (Blueprint $table) use ($isMysql) {
            if (!$isMysql) {
                $table->dropForeign(['mentor_id']);
            }
            $table->dropUnique(['course_id', 'mentor_id']);
            $table->dropColumn('mentor_id');

            $table->string('name')->after('course_id');
            $table->string('title')->nullable()->after('name');
            $table->text('bio')->nullable()->after('title');
            $table->string('photo_url')->nullable()->after('bio');

            $table->unique('course_id');
        });

        if ($isMysql) {
            Schema::table('course_mentors', function (Blueprint $table) {
                $table->foreign('course_id')->references('id')->on('courses')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        $isMysql = DB::getDriverName() === 'mysql';

        if ($isMysql) {
            Schema::table('course_mentors', function (Blueprint $table) {
                $table->dropForeign(['course_id']);
            });
        }

        Schema::table('course_mentors', function (Blueprint $table) {
            $table->dropUnique(['course_id']);
            $table->dropColumn(['name', 'title', 'bio', 'photo_url']);

            $table->uuid('mentor_id')->after('course_id');
            $table->foreign('mentor_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['course_id', 'mentor_id']);
        });

        if ($isMysql) {
            Schema::table('course_mentors', function (Blueprint $table) {
                $table->foreign('course_id')->references('id')->on('courses')->cascadeOnDelete();
            });
        }
    }
};

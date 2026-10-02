<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The public catalogue always filters on status + approval and sorts by
 * title, so index exactly that (and the level filter) to keep paging fast
 * once there are thousands of courses.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->index(['status', 'admin_approval_status', 'title'], 'courses_catalogue_title_index');
            $table->index(['status', 'admin_approval_status', 'classification', 'category_id'], 'courses_catalogue_level_index');
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropIndex('courses_catalogue_title_index');
            $table->dropIndex('courses_catalogue_level_index');
        });
    }
};

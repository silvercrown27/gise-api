<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lessons get their own approval status, so a super admin can approve or
 * reject them one by one (or all at once) instead of only per module.
 *
 * The column defaults to 'approved': every lesson that already exists stays
 * live. New lessons written by anyone other than a super admin are set to
 * 'pending' by the controller.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_lessons', function (Blueprint $table) {
            if (!Schema::hasColumn('course_lessons', 'admin_approval_status')) {
                $table->enum('admin_approval_status', ['pending', 'approved', 'rejected'])->default('approved')->after('is_preview');
            }
            if (!Schema::hasColumn('course_lessons', 'admin_rejection_reason')) {
                $table->text('admin_rejection_reason')->nullable()->after('admin_approval_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('course_lessons', function (Blueprint $table) {
            $columns = array_values(array_filter(
                ['admin_approval_status', 'admin_rejection_reason'],
                fn ($column) => Schema::hasColumn('course_lessons', $column)
            ));
            if ($columns) {
                $table->dropColumn($columns);
            }
        });
    }
};

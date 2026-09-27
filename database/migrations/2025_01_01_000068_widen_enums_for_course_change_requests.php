<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const NOTIFICATION_TYPES = [
        'payment', 'enrollment', 'certificate', 'rating', 'system',
        'quiz_review', 'mentor_application', 'instructor_approval', 'course_review',
        'exam_review', 'module_review',
    ];

    private const AUDIT_TARGETS = [
        'user', 'course', 'payment', 'module_quiz', 'cohort_mentor_application',
        'exam', 'course_module',
    ];

    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->enum('type', [...self::NOTIFICATION_TYPES, 'course_change_request'])->change();
        });

        Schema::table('admin_audit_logs', function (Blueprint $table) {
            $table->enum('target_type', [...self::AUDIT_TARGETS, 'course_change_request'])->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->enum('type', self::NOTIFICATION_TYPES)->change();
        });

        Schema::table('admin_audit_logs', function (Blueprint $table) {
            $table->enum('target_type', self::AUDIT_TARGETS)->nullable()->change();
        });
    }
};

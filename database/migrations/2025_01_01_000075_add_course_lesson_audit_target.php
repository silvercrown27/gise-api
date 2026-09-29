<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Lesson approvals are written to the admin audit log, so it needs a course_lesson target type. */
return new class extends Migration
{
    private const BASE = [
        'user', 'course', 'payment', 'module_quiz', 'cohort_mentor_application',
        'exam', 'course_module', 'course_change_request', 'course_material', 'course_lead',
    ];

    public function up(): void
    {
        Schema::table('admin_audit_logs', function (Blueprint $table) {
            $table->enum('target_type', [...self::BASE, 'course_lesson'])->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('admin_audit_logs', function (Blueprint $table) {
            $table->enum('target_type', self::BASE)->nullable()->change();
        });
    }
};

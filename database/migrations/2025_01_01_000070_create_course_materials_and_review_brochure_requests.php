<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Course materials mentors upload for admin review:
 *  - course_content: the full course document (outline, requirements,
 *    objectives, table of contents, modules and lessons with content)
 *  - brochure: the outline without lesson content, plus duration - the file
 *    emailed to people who request it
 *  - module_slides: slides for a module (PowerPoint and/or PDF)
 * The latest approved file of each kind is the live one.
 *
 * Brochure requests now wait for an admin before anything is emailed, and
 * notifications can link to the page where they're handled.
 */
return new class extends Migration
{
    private const NOTIFICATION_TYPES = [
        'payment', 'enrollment', 'certificate', 'rating', 'system',
        'quiz_review', 'mentor_application', 'instructor_approval', 'course_review',
        'exam_review', 'module_review', 'course_change_request',
    ];

    private const AUDIT_TARGETS = [
        'user', 'course', 'payment', 'module_quiz', 'cohort_mentor_application',
        'exam', 'course_module', 'course_change_request',
    ];

    public function up(): void
    {
        Schema::create('course_materials', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('course_id');
            $table->uuid('module_id')->nullable();
            $table->enum('type', ['course_content', 'brochure', 'module_slides']);
            $table->string('title');
            $table->string('file_url');
            $table->string('file_type', 10);
            $table->unsignedBigInteger('file_size')->nullable();
            $table->uuid('uploaded_by');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->uuid('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('course_id')->references('id')->on('courses')->cascadeOnDelete();
            $table->foreign('module_id')->references('id')->on('course_modules')->cascadeOnDelete();
            $table->foreign('uploaded_by')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('reviewed_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['course_id', 'type', 'status']);
            $table->index(['status', 'created_at']);
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->enum('type', [...self::NOTIFICATION_TYPES, 'course_material', 'brochure_request'])->change();
            $table->string('link')->nullable()->after('message');
        });

        Schema::table('admin_audit_logs', function (Blueprint $table) {
            $table->enum('target_type', [...self::AUDIT_TARGETS, 'course_material', 'course_lead'])->nullable()->change();
        });

        Schema::table('course_leads', function (Blueprint $table) {
            $table->enum('brochure_status', ['pending', 'sent', 'declined'])->nullable()->after('source');
            $table->uuid('reviewed_by')->nullable()->after('brochure_sent_at');
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $table->foreign('reviewed_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['source', 'brochure_status']);
        });

        // Requests made before review existed were emailed on the spot.
        DB::table('course_leads')->where('source', 'brochure')->update([
            'brochure_status' => DB::raw("case when brochure_sent_at is null then 'pending' else 'sent' end"),
        ]);

        // Brochures uploaded directly by an admin become approved materials.
        $superAdminId = DB::table('scholar_users')->where('role', 'admin')->orderBy('created_at')->value('id');
        foreach (DB::table('courses')->whereNotNull('brochure_url')->get(['id', 'brochure_url']) as $course) {
            if (!$superAdminId) {
                break;
            }
            DB::table('course_materials')->insert([
                'id' => (string) Str::uuid(),
                'course_id' => $course->id,
                'type' => 'brochure',
                'title' => 'Course brochure',
                'file_url' => $course->brochure_url,
                'file_type' => 'pdf',
                'uploaded_by' => $superAdminId,
                'status' => 'approved',
                'reviewed_by' => $superAdminId,
                'reviewed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('course_leads', function (Blueprint $table) {
            $table->dropForeign(['reviewed_by']);
            $table->dropIndex(['source', 'brochure_status']);
            $table->dropColumn(['brochure_status', 'reviewed_by', 'reviewed_at']);
        });

        Schema::table('admin_audit_logs', function (Blueprint $table) {
            $table->enum('target_type', self::AUDIT_TARGETS)->nullable()->change();
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->dropColumn('link');
            $table->enum('type', self::NOTIFICATION_TYPES)->change();
        });

        Schema::dropIfExists('course_materials');
    }
};

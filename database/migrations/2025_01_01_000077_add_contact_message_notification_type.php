<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Super admins are notified when someone fills in the contact form. */
return new class extends Migration
{
    private const TYPES = [
        'payment', 'enrollment', 'certificate', 'rating', 'system',
        'quiz_review', 'mentor_application', 'instructor_approval', 'course_review',
        'exam_review', 'module_review', 'course_change_request', 'course_material', 'brochure_request',
    ];

    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->enum('type', [...self::TYPES, 'contact_message'])->change();
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->enum('type', self::TYPES)->change();
        });
    }
};

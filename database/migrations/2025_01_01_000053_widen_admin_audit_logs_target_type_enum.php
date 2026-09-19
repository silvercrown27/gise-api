<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_audit_logs', function (Blueprint $table) {
            $table->enum('target_type', ['user', 'course', 'payment', 'module_quiz', 'cohort_mentor_application'])
                ->nullable()
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('admin_audit_logs', function (Blueprint $table) {
            $table->enum('target_type', ['user', 'course', 'payment'])
                ->nullable()
                ->change();
        });
    }
};

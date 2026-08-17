<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_leads', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->nullable();
            $table->uuid('course_id');
            $table->uuid('cohort_id')->nullable();
            $table->string('full_name');
            $table->string('email');
            $table->string('phone');
            $table->text('notes')->nullable();
            $table->enum('status', ['new', 'contacted', 'converted', 'waitlisted'])->default('new');
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('course_id')->references('id')->on('courses')->cascadeOnDelete();
            $table->foreign('cohort_id')->references('id')->on('cohorts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_leads');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_mentors', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('course_id');
            $table->uuid('mentor_id');
            $table->timestamp('assigned_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('course_id')->references('id')->on('courses')->cascadeOnDelete();
            $table->foreign('mentor_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['course_id', 'mentor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_mentors');
    }
};

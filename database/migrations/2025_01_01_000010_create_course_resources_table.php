<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_resources', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('course_id');
            $table->uuid('lesson_id')->nullable();
            $table->string('title');
            $table->string('file_url');
            $table->string('file_type')->nullable();
            $table->boolean('is_downloadable')->default(true);
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('course_id')->references('id')->on('courses')->cascadeOnDelete();
            $table->foreign('lesson_id')->references('id')->on('course_lessons')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_resources');
    }
};

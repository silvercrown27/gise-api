<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_lessons', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('module_id');
            $table->string('title');
            $table->enum('content_type', ['video', 'text', 'pdf', 'quiz']);
            $table->longText('content_url_or_body')->nullable();
            $table->integer('duration_minutes')->nullable();
            $table->integer('order_index')->default(0);
            $table->boolean('is_preview')->default(false);
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('module_id')->references('id')->on('course_modules')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_lessons');
    }
};

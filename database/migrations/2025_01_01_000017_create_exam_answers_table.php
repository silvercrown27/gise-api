<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_answers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('submission_id');
            $table->uuid('question_id');
            $table->text('answer_given')->nullable();
            $table->integer('marks_awarded')->nullable();
            $table->boolean('is_correct')->nullable();
            $table->timestamps();

            $table->foreign('submission_id')->references('id')->on('exam_submissions')->cascadeOnDelete();
            $table->foreign('question_id')->references('id')->on('exam_questions')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_answers');
    }
};

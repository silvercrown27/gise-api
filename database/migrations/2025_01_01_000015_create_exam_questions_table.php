<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_questions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('exam_id');
            $table->text('question_text');
            $table->enum('question_type', ['mcq', 'true_false', 'short_answer', 'essay']);
            $table->json('options')->nullable();
            $table->text('correct_answer')->nullable();
            $table->integer('marks')->default(0);
            $table->integer('order_index')->default(0);
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('exam_id')->references('id')->on('exams')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_questions');
    }
};

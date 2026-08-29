<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('module_quiz_questions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('quiz_id');
            $table->text('question_text');
            $table->json('options');
            $table->string('correct_option_key');
            $table->integer('order_index')->default(0);
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('quiz_id')->references('id')->on('module_quizzes')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('module_quiz_questions');
    }
};

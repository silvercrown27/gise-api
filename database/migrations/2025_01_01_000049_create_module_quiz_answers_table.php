<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('module_quiz_answers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('attempt_id');
            $table->uuid('question_id');
            $table->string('selected_option_key')->nullable();
            $table->boolean('is_correct')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('attempt_id')->references('id')->on('module_quiz_attempts')->cascadeOnDelete();
            $table->foreign('question_id')->references('id')->on('module_quiz_questions')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('module_quiz_answers');
    }
};

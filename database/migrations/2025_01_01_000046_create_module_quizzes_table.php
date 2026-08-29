<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('module_quizzes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('module_id');
            $table->string('title');
            $table->text('instructions')->nullable();
            $table->integer('passing_percent')->default(70);
            $table->integer('max_attempts')->default(3);
            $table->integer('cooldown_hours')->default(24);
            $table->softDeletes();
            $table->timestamps();

            $table->unique('module_id');
            $table->foreign('module_id')->references('id')->on('course_modules')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('module_quizzes');
    }
};

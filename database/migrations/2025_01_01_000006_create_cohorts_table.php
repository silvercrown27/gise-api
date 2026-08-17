<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cohorts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('course_id');
            $table->string('label');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->enum('mode', ['online', 'in_person', 'hybrid'])->default('online');
            $table->integer('capacity')->default(0);
            $table->integer('seats_taken')->default(0);
            $table->enum('status', ['upcoming', 'open', 'closed', 'completed'])->default('upcoming');
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('course_id')->references('id')->on('courses')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cohorts');
    }
};

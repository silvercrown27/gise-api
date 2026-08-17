<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('enrollment_id');
            $table->string('certificate_number')->unique();
            $table->string('certificate_url')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamps();

            $table->unique('enrollment_id');
            $table->foreign('enrollment_id')->references('id')->on('enrollments')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificates');
    }
};

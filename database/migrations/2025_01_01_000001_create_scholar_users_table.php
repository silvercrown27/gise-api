<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scholar_users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('email');
            $table->enum('role', ['student', 'instructor', 'admin']);
            $table->string('phone')->nullable();
            $table->string('avatar_url')->nullable();
            $table->enum('status', ['active', 'suspended', 'pending_verification'])->default('pending_verification');
            $table->timestamp('last_login_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scholar_users');
    }
};

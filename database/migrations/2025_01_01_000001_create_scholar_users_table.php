<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scholar_users', function (Blueprint $table) {
            // scholar_users.id is not a separately generated UUID — it is always
            // set to the same value as the owning users.id (a shared-identity /
            // "extends" relationship, not a foreign key column). This keeps a
            // single authoritative id per person and means deleting the users
            // row (account deletion) cleanly cascades here with no separate
            // user_id column to keep in sync.
            $table->uuid('id')->primary();
            $table->enum('role', ['learner', 'instructor', 'admin']);
            $table->string('phone')->nullable();
            $table->string('avatar_url')->nullable();
            $table->enum('status', ['active', 'suspended', 'pending_verification'])->default('pending_verification');
            $table->timestamp('last_login_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scholar_users');
    }
};

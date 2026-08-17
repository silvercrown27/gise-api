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
            // Denormalized copy of users.email — kept in sync at write time so
            // this table can be queried/joined on without hitting users, which
            // is treated as the account-lifecycle table (deleted on account
            // deletion; scholar_users cascades off it, not the other way round).
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

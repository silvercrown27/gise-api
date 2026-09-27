<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Accounts no longer wait on verification: email verification is optional and
 * tracked separately on users.email_verified_at. Instructors are still vetted
 * through instructor_profiles.approval_status.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scholar_users', function (Blueprint $table) {
            $table->enum('status', ['active', 'suspended', 'pending_verification'])->default('active')->change();
        });

        DB::table('scholar_users')->where('status', 'pending_verification')->update(['status' => 'active']);
    }

    public function down(): void
    {
        Schema::table('scholar_users', function (Blueprint $table) {
            $table->enum('status', ['active', 'suspended', 'pending_verification'])->default('pending_verification')->change();
        });
    }
};

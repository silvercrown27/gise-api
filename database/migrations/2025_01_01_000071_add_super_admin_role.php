<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Two staff tiers:
 *  - super_admin: approves instructors, courses and course content, manages
 *    roles, and publishes content directly;
 *  - admin: runs the platform (courses, modules, lessons, documents, cohorts),
 *    but course content they change waits for a super admin.
 * The first-created admin - who already owns every course - becomes the
 * first super admin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scholar_users', function (Blueprint $table) {
            $table->enum('role', ['student', 'instructor', 'admin', 'super_admin'])->change();
        });

        $firstAdmin = DB::table('scholar_users')
            ->where('role', 'admin')
            ->whereNull('deleted_at')
            ->orderBy('created_at')
            ->value('id');

        if ($firstAdmin) {
            DB::table('scholar_users')->where('id', $firstAdmin)->update(['role' => 'super_admin']);
        }
    }

    public function down(): void
    {
        DB::table('scholar_users')->where('role', 'super_admin')->update(['role' => 'admin']);

        Schema::table('scholar_users', function (Blueprint $table) {
            $table->enum('role', ['student', 'instructor', 'admin'])->change();
        });
    }
};

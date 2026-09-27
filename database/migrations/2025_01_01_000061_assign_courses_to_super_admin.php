<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Courses are now centrally managed: every course moves under the super admin
 * (the first-created admin). Previous instructor owners keep no special access
 * and apply to mentor cohorts like any other instructor.
 */
return new class extends Migration
{
    public function up(): void
    {
        $superAdminId = DB::table('scholar_users')
            ->where('role', 'admin')
            ->whereNull('deleted_at')
            ->orderBy('created_at')
            ->value('id');

        if ($superAdminId) {
            DB::table('courses')->update(['instructor_id' => $superAdminId]);
        }

        // seats_taken was never maintained after seeding - recount it from
        // live enrollments. Enrollment model events keep it in sync from here.
        DB::table('cohorts')->update([
            'seats_taken' => DB::raw(
                "(select count(*) from enrollments where enrollments.cohort_id = cohorts.id"
                . " and enrollments.deleted_at is null and enrollments.enrollment_status != 'dropped')"
            ),
        ]);
    }

    public function down(): void
    {
        // Previous owners aren't recorded, so ownership can't be restored.
    }
};

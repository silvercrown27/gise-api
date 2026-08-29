<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE enrollments MODIFY enrollment_status ENUM('active', 'completed', 'dropped', 'failed') NOT NULL DEFAULT 'active'");
        }

        Schema::table('enrollments', function (Blueprint $table) {
            $table->uuid('failed_module_id')->nullable()->after('enrollment_status');
            $table->foreign('failed_module_id')->references('id')->on('course_modules')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropForeign(['failed_module_id']);
            $table->dropColumn('failed_module_id');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE enrollments MODIFY enrollment_status ENUM('active', 'completed', 'dropped') NOT NULL DEFAULT 'active'");
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_modules', function (Blueprint $table) {
            $table->integer('unlock_after_days')->default(0)->after('order_index');
        });

        Schema::table('course_lessons', function (Blueprint $table) {
            $table->integer('unlock_after_days')->nullable()->after('order_index');
        });
    }

    public function down(): void
    {
        Schema::table('course_modules', function (Blueprint $table) {
            $table->dropColumn('unlock_after_days');
        });

        Schema::table('course_lessons', function (Blueprint $table) {
            $table->dropColumn('unlock_after_days');
        });
    }
};

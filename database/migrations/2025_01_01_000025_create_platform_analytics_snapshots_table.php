<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_analytics_snapshots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->date('snapshot_date')->unique();
            $table->integer('total_learners')->default(0);
            $table->integer('total_instructors')->default(0);
            $table->integer('total_courses')->default(0);
            $table->integer('total_enrollments')->default(0);
            $table->bigInteger('total_revenue')->default(0);
            $table->integer('active_courses_count')->default(0);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_analytics_snapshots');
    }
};

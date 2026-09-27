<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Software and tools a course uses (e.g. Ansys, ArcGIS). A learner can register
 * with or without the licences; the licence price is added to the cohort fee.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tools', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('vendor')->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('licence_price')->default(0);
            $table->string('currency', 3)->default('USD');
            // e.g. "12-month student licence"
            $table->string('licence_term')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('course_tools', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('course_id');
            $table->uuid('tool_id');
            // Overrides tools.licence_price for this course when set.
            $table->unsignedInteger('licence_price')->nullable();
            $table->timestamps();

            $table->foreign('course_id')->references('id')->on('courses')->cascadeOnDelete();
            $table->foreign('tool_id')->references('id')->on('tools')->cascadeOnDelete();
            $table->unique(['course_id', 'tool_id']);
            $table->index('tool_id');
        });

        Schema::table('enrollments', function (Blueprint $table) {
            $table->boolean('with_licences')->default(false)->after('cohort_id');
            // What the learner was quoted at registration: cohort fee plus licences if chosen.
            $table->unsignedInteger('quoted_fee')->nullable()->after('with_licences');
            $table->string('currency', 3)->nullable()->after('quoted_fee');
        });
    }

    public function down(): void
    {
        Schema::table('enrollments', fn (Blueprint $table) => $table->dropColumn(['with_licences', 'quoted_fee', 'currency']));
        Schema::dropIfExists('course_tools');
        Schema::dropIfExists('tools');
    }
};

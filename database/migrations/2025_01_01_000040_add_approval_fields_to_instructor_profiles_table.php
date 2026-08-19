<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instructor_profiles', function (Blueprint $table) {
            $table->enum('approval_status', ['pending', 'approved', 'banned'])->default('pending')->after('verification_status');
            $table->string('specialization_one')->nullable()->after('expertise_tags');
            $table->string('specialization_two')->nullable()->after('specialization_one');
            $table->timestamp('approved_at')->nullable()->after('approval_status');
            $table->uuid('approved_by')->nullable()->after('approved_at');

            $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('instructor_profiles', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
            $table->dropColumn(['approval_status', 'specialization_one', 'specialization_two', 'approved_at', 'approved_by']);
        });
    }
};

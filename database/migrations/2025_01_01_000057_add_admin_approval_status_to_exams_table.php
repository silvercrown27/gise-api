<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->enum('admin_approval_status', ['pending', 'approved', 'rejected'])
                ->default('approved')
                ->after('attempts_allowed');
            $table->text('admin_rejection_reason')->nullable()->after('admin_approval_status');
        });
    }

    public function down(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->dropColumn(['admin_approval_status', 'admin_rejection_reason']);
        });
    }
};

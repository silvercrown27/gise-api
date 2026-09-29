<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paid payments get a permanent, sequential invoice number (INV-2026-00001),
 * assigned once and never reused, so a learner's invoice always matches ours.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('payments', 'invoice_number')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->string('invoice_number', 30)->nullable()->unique()->after('reference');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('payments', 'invoice_number')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropUnique(['invoice_number']);
                $table->dropColumn('invoice_number');
            });
        }
    }
};

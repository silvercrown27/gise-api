<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payments made through Paystack at registration. A payment remembers what
 * the learner chose (cohort, licences) so the enrollment can be created once
 * Paystack confirms the charge - from the callback or the webhook, whichever
 * lands first.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (!Schema::hasColumn('payments', 'cohort_id')) {
                $table->uuid('cohort_id')->nullable()->after('course_id');
                $table->foreign('cohort_id')->references('id')->on('cohorts')->nullOnDelete();
            }
            if (!Schema::hasColumn('payments', 'with_licences')) {
                $table->boolean('with_licences')->default(false)->after('cohort_id');
            }
            if (!Schema::hasColumn('payments', 'reference')) {
                $table->string('reference', 100)->nullable()->unique()->after('payment_gateway');
            }
            if (!Schema::hasColumn('payments', 'channel')) {
                $table->string('channel', 50)->nullable()->after('reference');
            }
            if (!Schema::hasColumn('payments', 'gateway_response')) {
                $table->json('gateway_response')->nullable()->after('gateway_transaction_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (Schema::hasColumn('payments', 'cohort_id')) {
                $table->dropForeign(['cohort_id']);
            }
        });

        Schema::table('payments', function (Blueprint $table) {
            if (Schema::hasColumn('payments', 'reference')) {
                $table->dropUnique(['reference']);
            }
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(array_values(array_filter(
                ['cohort_id', 'with_licences', 'reference', 'channel', 'gateway_response'],
                fn ($column) => Schema::hasColumn('payments', $column)
            )));
        });
    }
};

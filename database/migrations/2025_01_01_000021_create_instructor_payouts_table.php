<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instructor_payouts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('instructor_id');
            $table->date('period_start');
            $table->date('period_end');
            $table->bigInteger('gross_amount');
            $table->bigInteger('platform_fee');
            $table->bigInteger('net_amount');
            $table->enum('status', ['pending', 'paid'])->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('instructor_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instructor_payouts');
    }
};

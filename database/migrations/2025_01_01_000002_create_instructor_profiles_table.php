<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instructor_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->text('bio')->nullable();
            $table->string('expertise_tags')->nullable();
            $table->enum('payout_method', ['bank', 'mobile_money', 'paypal'])->nullable();
            $table->text('payout_details')->nullable();
            $table->decimal('average_rating', 3, 2)->default(0);
            $table->enum('verification_status', ['pending', 'verified'])->default('pending');
            $table->softDeletes();
            $table->timestamps();

            $table->unique('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instructor_profiles');
    }
};

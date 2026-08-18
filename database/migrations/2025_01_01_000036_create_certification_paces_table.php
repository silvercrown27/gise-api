<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certification_paces', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('certification_level_id');
            $table->string('name');
            $table->enum('certification_track', ['full', 'partial']);
            $table->integer('duration_weeks');
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('certification_level_id')->references('id')->on('certification_levels')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certification_paces');
    }
};

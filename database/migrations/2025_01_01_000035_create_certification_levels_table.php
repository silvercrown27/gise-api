<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certification_levels', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('certification_type_id');
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->integer('order_index')->default(0);
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('certification_type_id')->references('id')->on('certification_types')->cascadeOnDelete();
            $table->unique(['certification_type_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certification_levels');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('instructor_id');
            $table->uuid('category_id')->nullable();
            $table->string('code')->unique();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('tagline')->nullable();
            $table->string('short_description')->nullable();
            $table->longText('full_description')->nullable();
            $table->json('outline')->nullable();
            $table->string('thumbnail_url')->nullable();
            $table->bigInteger('price')->default(0);
            $table->bigInteger('original_price')->nullable();
            $table->string('currency', 3)->default('USD');
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->enum('level', ['beginner', 'intermediate', 'advanced', 'career_switch'])->default('beginner');
            $table->enum('tag', ['beginner_friendly', 'high_demand', 'portfolio_track', 'career_switch', 'leadership', 'new'])->nullable();
            $table->enum('spine', ['green', 'blue', 'black', 'bright'])->nullable();
            $table->enum('mode', ['online', 'in_person', 'hybrid'])->default('online');
            $table->integer('duration_weeks')->nullable();
            $table->string('language')->default('en');
            $table->timestamp('published_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('instructor_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('category_id')->references('id')->on('categories')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};

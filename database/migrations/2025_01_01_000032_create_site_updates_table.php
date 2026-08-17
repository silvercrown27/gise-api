<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_updates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->enum('type', [
                'signup',
                'subscription',
                'new_mentor',
                'course_published',
                'inquiry',
                'complaint',
            ]);
            $table->nullableUuidMorphs('subject');
            $table->uuid('causer_id')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->boolean('is_read')->default(false);
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('causer_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_updates');
    }
};

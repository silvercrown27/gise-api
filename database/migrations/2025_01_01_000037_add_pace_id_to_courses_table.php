<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->uuid('pace_id')->nullable()->after('category_id');
            $table->foreign('pace_id')->references('id')->on('certification_paces')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropForeign(['pace_id']);
            $table->dropColumn('pace_id');
        });
    }
};

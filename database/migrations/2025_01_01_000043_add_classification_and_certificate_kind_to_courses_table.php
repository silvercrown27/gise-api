<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->enum('classification', ['o_level', 'a_level', 'skills_professional'])
                ->default('skills_professional')
                ->after('pace_id');
            $table->enum('certificate_kind', ['recognized', 'completion'])
                ->default('completion')
                ->after('classification');
            $table->string('recognized_body')->nullable()->after('certificate_kind');
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn(['classification', 'certificate_kind', 'recognized_body']);
        });
    }
};

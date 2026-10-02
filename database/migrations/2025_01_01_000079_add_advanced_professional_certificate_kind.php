<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Skills & professional courses can award an advanced professional certification. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->enum('certificate_kind', ['recognized', 'completion', 'advanced_professional'])
                ->default('completion')
                ->change();
        });
    }

    public function down(): void
    {
        DB::table('courses')->where('certificate_kind', 'advanced_professional')->update(['certificate_kind' => 'completion']);

        Schema::table('courses', function (Blueprint $table) {
            $table->enum('certificate_kind', ['recognized', 'completion'])
                ->default('completion')
                ->change();
        });
    }
};

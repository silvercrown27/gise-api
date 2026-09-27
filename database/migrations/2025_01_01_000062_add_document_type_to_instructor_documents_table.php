<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instructor_documents', function (Blueprint $table) {
            $table->enum('document_type', ['national_id', 'cv', 'academic_certificate', 'other'])
                ->default('other')
                ->after('instructor_id');
        });
    }

    public function down(): void
    {
        Schema::table('instructor_documents', function (Blueprint $table) {
            $table->dropColumn('document_type');
        });
    }
};

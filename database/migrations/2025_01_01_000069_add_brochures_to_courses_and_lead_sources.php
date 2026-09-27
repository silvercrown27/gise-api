<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Downloadable course brochures, and brochure requests captured as leads.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->string('brochure_url')->nullable()->after('thumbnail_url');
        });

        Schema::table('course_leads', function (Blueprint $table) {
            $table->enum('source', ['enquiry', 'brochure'])->default('enquiry')->after('phone');
            $table->timestamp('brochure_sent_at')->nullable()->after('source');
            $table->index(['course_id', 'source']);
        });
    }

    public function down(): void
    {
        Schema::table('course_leads', function (Blueprint $table) {
            // MySQL may be using this index for the course_id foreign key; give
            // the key its own index before dropping the compound one.
            if (!Schema::hasIndex('course_leads', ['course_id'])) {
                $table->index('course_id');
            }
            $table->dropIndex(['course_id', 'source']);
            $table->dropColumn(['source', 'brochure_sent_at']);
        });

        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn('brochure_url');
        });
    }
};

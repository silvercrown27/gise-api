<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Catalogue structure: three fixed distinctions (courses.classification) and,
 * under each, admin-managed sub-distinctions - subjects for O/A-Level,
 * subcategories such as "Business Administration" for Skills & Professional.
 * Sub-distinctions are categories scoped to one classification.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->enum('classification', ['o_level', 'a_level', 'skills_professional'])
                ->default('skills_professional')
                ->after('slug');
            $table->index('classification');
        });

        // Place each existing category under the distinction most of its courses use.
        foreach (DB::table('categories')->pluck('id') as $categoryId) {
            $classification = DB::table('courses')
                ->where('category_id', $categoryId)
                ->whereNull('deleted_at')
                ->select('classification', DB::raw('count(*) as total'))
                ->groupBy('classification')
                ->orderByDesc('total')
                ->value('classification');

            if ($classification) {
                DB::table('categories')->where('id', $categoryId)->update(['classification' => $classification]);
            }
        }

        // The catalogue filters on these on every request.
        Schema::table('courses', function (Blueprint $table) {
            $table->index(['status', 'admin_approval_status', 'classification'], 'courses_catalogue_index');
            $table->index('category_id', 'courses_category_index');
            $table->index('title', 'courses_title_index');
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropIndex('courses_catalogue_index');
            $table->dropIndex('courses_category_index');
            $table->dropIndex('courses_title_index');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropIndex(['classification']);
            $table->dropColumn('classification');
        });
    }
};

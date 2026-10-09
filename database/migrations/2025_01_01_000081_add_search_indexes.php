<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes behind the search boxes and the busiest admin lists (see App\Support\TextSearch).
 *
 * FULLTEXT indexes only exist on MySQL / MariaDB; other databases (SQLite in tests) skip them
 * and search falls back to a plain contains-match. Each index is skipped if it already exists.
 */
return new class extends Migration
{
    private function isMysql(): bool
    {
        return in_array(DB::getDriverName(), ['mysql', 'mariadb'], true);
    }

    public function up(): void
    {
        if ($this->isMysql()) {
            // The column lists must match what the code passes to MATCH(...) exactly.
            $this->fulltext('courses', 'courses_search_ft', ['title', 'code', 'tagline', 'short_description']);
            $this->fulltext('users', 'users_search_ft', ['name', 'email']);
            $this->fulltext('instructor_profiles', 'instructor_profiles_search_ft', ['expertise_tags']);
        }

        // Plain indexes for the default orderings and filters of the admin course list.
        $this->index('courses', 'courses_deleted_created_index', ['deleted_at', 'created_at']);
        $this->index('courses', 'courses_approval_created_index', ['admin_approval_status', 'created_at']);
        $this->index('courses', 'courses_status_published_index', ['status', 'published_at']);
        $this->index('scholar_users', 'scholar_users_created_index', ['created_at']);
        $this->index('instructor_profiles', 'instructor_profiles_approval_created_index', ['approval_status', 'created_at']);
    }

    public function down(): void
    {
        foreach ([
            ['courses', 'courses_search_ft'], ['users', 'users_search_ft'], ['instructor_profiles', 'instructor_profiles_search_ft'],
            ['courses', 'courses_deleted_created_index'], ['courses', 'courses_approval_created_index'], ['courses', 'courses_status_published_index'],
            ['scholar_users', 'scholar_users_created_index'], ['instructor_profiles', 'instructor_profiles_approval_created_index'],
        ] as [$table, $name]) {
            if (Schema::hasTable($table) && Schema::hasIndex($table, $name)) {
                Schema::table($table, fn (Blueprint $t) => $t->dropIndex($name));
            }
        }
    }

    private function fulltext(string $table, string $name, array $columns): void
    {
        if (Schema::hasTable($table) && Schema::hasColumns($table, $columns) && !Schema::hasIndex($table, $name)) {
            Schema::table($table, fn (Blueprint $t) => $t->fullText($columns, $name));
        }
    }

    private function index(string $table, string $name, array $columns): void
    {
        if (Schema::hasTable($table) && Schema::hasColumns($table, $columns) && !Schema::hasIndex($table, $name)) {
            Schema::table($table, fn (Blueprint $t) => $t->index($columns, $name));
        }
    }
};

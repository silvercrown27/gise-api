<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes for the queries every public page and every signed-in request runs:
 * the catalogue's "upcoming cohorts of this course" subqueries, the role
 * lookups behind every permission check, the notification bell, and the
 * ordered public lists. Each is skipped if an index with that name exists.
 */
return new class extends Migration
{
    /** table => [index name => columns] */
    private const INDEXES = [
        'cohorts' => [
            'cohorts_course_start_status_index' => ['course_id', 'start_date', 'status'],
            'cohorts_status_start_index' => ['status', 'start_date'],
        ],
        'scholar_users' => [
            'scholar_users_role_index' => ['role'],
        ],
        'notifications' => [
            'notifications_user_read_created_index' => ['user_id', 'is_read', 'created_at'],
        ],
        'testimonials' => [
            'testimonials_order_index' => ['order_index'],
        ],
        'team_members' => [
            'team_members_order_index' => ['order_index'],
        ],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            foreach ($indexes as $name => $columns) {
                if (!Schema::hasTable($table) || Schema::hasIndex($table, $name) || !Schema::hasColumns($table, $columns)) {
                    continue;
                }
                Schema::table($table, fn (Blueprint $t) => $t->index($columns, $name));
            }
        }
    }

    public function down(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            foreach (array_keys($indexes) as $name) {
                if (Schema::hasTable($table) && Schema::hasIndex($table, $name)) {
                    Schema::table($table, fn (Blueprint $t) => $t->dropIndex($name));
                }
            }
        }
    }
};

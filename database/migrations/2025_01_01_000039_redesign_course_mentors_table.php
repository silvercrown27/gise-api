<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * course_mentors stops pointing at a user and instead stores the mentor's
 * display details (one per course).
 *
 * Every step checks the current schema first: databases in the wild differ
 * (the original dev database had already lost the mentor_id foreign key),
 * and MySQL doesn't roll back DDL, so a failed run can leave the table half
 * changed. Re-running this migration picks up wherever it stopped.
 */
return new class extends Migration
{
    private const TABLE = 'course_mentors';

    public function up(): void
    {
        // MySQL needs an index on course_id's FK at all times; it may be using
        // the compound (course_id, mentor_id) unique index, so drop the FK
        // while that index changes and re-add it at the end.
        $this->dropForeignIfExists('course_id');
        $this->dropForeignIfExists('mentor_id');
        $this->dropIndexIfExists(['course_id', 'mentor_id']);

        Schema::table(self::TABLE, function (Blueprint $table) {
            if (Schema::hasColumn(self::TABLE, 'mentor_id')) {
                $table->dropColumn('mentor_id');
            }
        });

        Schema::table(self::TABLE, function (Blueprint $table) {
            if (!Schema::hasColumn(self::TABLE, 'name')) {
                $table->string('name')->after('course_id');
            }
            if (!Schema::hasColumn(self::TABLE, 'title')) {
                $table->string('title')->nullable()->after('name');
            }
            if (!Schema::hasColumn(self::TABLE, 'bio')) {
                $table->text('bio')->nullable()->after('title');
            }
            if (!Schema::hasColumn(self::TABLE, 'photo_url')) {
                $table->string('photo_url')->nullable()->after('bio');
            }
        });

        if (!$this->hasIndex(['course_id'], unique: true)) {
            Schema::table(self::TABLE, fn (Blueprint $table) => $table->unique('course_id'));
        }

        if (!$this->hasForeign('course_id')) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->foreign('course_id')->references('id')->on('courses')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        $this->dropForeignIfExists('course_id');
        $this->dropIndexIfExists(['course_id']);

        Schema::table(self::TABLE, function (Blueprint $table) {
            $table->dropColumn(array_values(array_filter(
                ['name', 'title', 'bio', 'photo_url'],
                fn ($column) => Schema::hasColumn(self::TABLE, $column)
            )));
        });

        Schema::table(self::TABLE, function (Blueprint $table) {
            if (!Schema::hasColumn(self::TABLE, 'mentor_id')) {
                $table->uuid('mentor_id')->after('course_id');
            }
        });

        Schema::table(self::TABLE, function (Blueprint $table) {
            $table->foreign('mentor_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['course_id', 'mentor_id']);
            $table->foreign('course_id')->references('id')->on('courses')->cascadeOnDelete();
        });
    }

    private function hasForeign(string $column): bool
    {
        return collect(Schema::getForeignKeys(self::TABLE))
            ->contains(fn ($fk) => $fk['columns'] === [$column]);
    }

    private function dropForeignIfExists(string $column): void
    {
        foreach (Schema::getForeignKeys(self::TABLE) as $fk) {
            if ($fk['columns'] === [$column]) {
                // SQLite can only drop a key by its columns; MySQL needs the real
                // name, which older databases may not have in Laravel's format.
                $key = Schema::getConnection()->getDriverName() === 'sqlite' ? $fk['columns'] : $fk['name'];
                Schema::table(self::TABLE, fn (Blueprint $table) => $table->dropForeign($key));
            }
        }
    }

    private function hasIndex(array $columns, bool $unique = false): bool
    {
        return collect(Schema::getIndexes(self::TABLE))
            ->contains(fn ($index) => $index['columns'] === $columns && (!$unique || $index['unique']) && !$index['primary']);
    }

    private function dropIndexIfExists(array $columns): void
    {
        foreach (Schema::getIndexes(self::TABLE) as $index) {
            if ($index['columns'] === $columns && !$index['primary']) {
                Schema::table(self::TABLE, fn (Blueprint $table) => $table->dropIndex($index['name']));
            }
        }
    }
};

<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    /** True once a test has committed its rows so a FULLTEXT search could see them. */
    private bool $committedForFulltext = false;

    /**
     * Tests normally run inside a transaction that is rolled back, which is fast. On MySQL, though,
     * a FULLTEXT index only sees committed rows, so a request that searches (?q=...) would find
     * nothing. Commit what the test has created just before such a request; tearDown then empties
     * the tables that were used. On SQLite (the default) none of this happens.
     */
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        $this->commitForFulltext(str_contains((string) $uri, 'q=') || isset($parameters['q']));

        return parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
    }

    protected function commitForFulltext(bool $needed = true): void
    {
        if (!$needed || !in_array(DB::getDriverName(), ['mysql', 'mariadb'], true) || DB::transactionLevel() === 0) {
            return;
        }

        DB::commit();
        DB::beginTransaction();
        $this->committedForFulltext = true;
    }

    protected function tearDown(): void
    {
        if ($this->committedForFulltext && $this->app) {
            while (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            foreach (DB::select('SHOW TABLES') as $row) {
                $table = array_values((array) $row)[0];
                // Only tables that actually received rows need emptying (a truncate of all ~80 is slow).
                if ($table !== 'migrations' && DB::table($table)->exists()) {
                    DB::table($table)->truncate();
                }
            }
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        parent::tearDown();
    }
}

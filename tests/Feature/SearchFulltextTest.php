<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use App\Models\ScholarUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Search behaviour that depends on the database. On MySQL / MariaDB it exercises the real FULLTEXT
 * indexes; elsewhere it checks the contains-match fallback gives the same answers.
 */
class SearchFulltextTest extends TestCase
{
    use RefreshDatabase;

    private function isMysql(): bool
    {
        return in_array(DB::getDriverName(), ['mysql', 'mariadb'], true);
    }

    private function admin(): void
    {
        $user = User::factory()->create();
        ScholarUser::factory()->create(['id' => $user->id, 'role' => 'super_admin', 'status' => 'active']);
        Sanctum::actingAs($user);
    }

    private function course(string $title, array $attrs = []): Course
    {
        return Course::factory()->create($attrs + ['title' => $title, 'status' => 'published', 'tagline' => null, 'short_description' => null]);
    }

    private function adminTitles(string $q): array
    {
        return collect($this->getJson('/api/courses/admin?q=' . urlencode($q))->assertOk()->json('data.data'))->pluck('title')->sort()->values()->all();
    }

    private function publicTitles(string $q): array
    {
        return collect($this->getJson('/api/courses?q=' . urlencode($q))->assertOk()->json('data.data'))->pluck('title')->sort()->values()->all();
    }

    public function test_the_fulltext_indexes_exist_on_mysql(): void
    {
        if (!in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped('FULLTEXT indexes only exist on MySQL / MariaDB.');
        }

        foreach ([['courses', 'courses_search_ft'], ['users', 'users_search_ft'], ['instructor_profiles', 'instructor_profiles_search_ft']] as [$table, $index]) {
            $this->assertTrue(\Illuminate\Support\Facades\Schema::hasIndex($table, $index), "{$table}.{$index}");
        }
    }

    public function test_words_prefixes_and_several_words_are_found(): void
    {
        $this->admin();
        $this->course('Procurement and Supply Chain Management');
        $this->course('Strategic Sourcing');
        $this->course('Inventory Management and Warehouse Optimization');

        $this->assertSame(['Procurement and Supply Chain Management'], $this->adminTitles('procure'), 'a word prefix');
        $this->assertSame(['Procurement and Supply Chain Management'], $this->adminTitles('SUPPLY chain'), 'every word must be present, any case');
        $this->assertSame(['Inventory Management and Warehouse Optimization', 'Procurement and Supply Chain Management'], $this->adminTitles('management'));
        $this->assertSame([], $this->adminTitles('zzzzzz'));
    }

    public function test_codes_slugs_taglines_and_short_terms_are_found(): void
    {
        $this->admin();
        $this->course('Alpha', ['code' => 'P091', 'slug' => 'alpha-slug-here', 'tagline' => 'Buy better with confidence']);
        $this->course('Beta', ['code' => 'IGCSE-04', 'slug' => 'beta']);

        $this->assertSame(['Alpha'], $this->adminTitles('P091'), 'course code');
        $this->assertSame(['Alpha'], $this->adminTitles('alpha-slug'), 'slug from its start');
        $this->assertSame(['Alpha'], $this->adminTitles('confidence'), 'tagline');
        $this->assertSame(['Beta'], $this->adminTitles('igcse-04'), 'a hyphenated code');
        $this->assertSame(['Beta'], $this->adminTitles('04'), 'a two-character term still works');
    }

    public function test_a_stopword_in_the_search_does_not_hide_results(): void
    {
        $this->admin();
        $this->course('Planning for Emergencies');

        $this->assertSame(['Planning for Emergencies'], $this->adminTitles('planning for'));
        $this->assertSame(['Planning for Emergencies'], $this->adminTitles('for'));
    }

    public function test_symbols_and_operators_are_harmless(): void
    {
        $this->admin();
        $this->course('Safe Course');

        foreach (['+', '-', '*', '"', '()', '@x', '<>~', '100%', "o'brien", '+safe -course'] as $term) {
            $this->getJson('/api/courses/admin?q=' . urlencode($term))->assertOk();
            $this->getJson('/api/courses?q=' . urlencode($term))->assertOk();
        }
        $this->assertSame(['Safe Course'], $this->adminTitles('+safe -course'), 'operators are ignored, not interpreted');
    }

    public function test_category_names_and_the_public_catalogue_search_work_too(): void
    {
        $this->admin();
        $cat = Category::factory()->create(['name' => 'Logistics Studio']);
        $this->course('In The Studio', ['category_id' => $cat->id, 'admin_approval_status' => 'approved']);
        $this->course('Other', ['admin_approval_status' => 'approved']);

        $this->assertSame(['In The Studio'], $this->adminTitles('Logistics'));
        $this->assertSame(['In The Studio'], $this->publicTitles('studio'));
    }

    public function test_people_can_be_found_by_name_email_and_phone(): void
    {
        $this->admin();
        $u = User::factory()->create(['name' => 'Wanjiru Kamau', 'email' => 'wanjiru.kamau@example.org']);
        ScholarUser::factory()->create(['id' => $u->id, 'role' => 'student', 'phone' => '+254722111222', 'status' => 'active']);
        $names = fn (string $q) => collect($this->getJson('/api/scholar-users?role=student&q=' . urlencode($q))->json('data.data'))->pluck('user.name')->all();

        $this->assertSame(['Wanjiru Kamau'], $names('wanji'), 'name prefix');
        $this->assertSame(['Wanjiru Kamau'], $names('kamau wanjiru'), 'words in any order');
        $this->assertSame(['Wanjiru Kamau'], $names('wanjiru.kamau'), 'email');
        $this->assertSame(['Wanjiru Kamau'], $names('722111'), 'part of a phone number');
        $this->assertSame([], $names('nobody'));
    }
}

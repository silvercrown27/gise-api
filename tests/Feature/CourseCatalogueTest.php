<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Cohort;
use App\Models\Course;
use App\Models\CourseTool;
use App\Models\ScholarUser;
use App\Models\Tool;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CourseCatalogueTest extends TestCase
{
    use RefreshDatabase;

    private function publishedCourse(array $attributes = []): Course
    {
        return Course::factory()->create(array_merge([
            'status' => 'published',
            'admin_approval_status' => 'approved',
            'classification' => 'skills_professional',
            'price' => 500,
        ], $attributes));
    }

    public function test_filters_by_level_and_sub_distinction(): void
    {
        $algebra = Category::factory()->create(['slug' => 'mathematics', 'classification' => 'a_level']);
        $inMaths = $this->publishedCourse(['classification' => 'a_level', 'category_id' => $algebra->id, 'title' => 'Vectors']);
        $this->publishedCourse(['classification' => 'o_level', 'title' => 'Biology']);
        $this->publishedCourse(['title' => 'Business Analytics']);

        $response = $this->getJson('/api/courses?classification=a_level&category=mathematics');

        $response->assertStatus(200);
        $this->assertSame([(string) $inMaths->id], collect($response->json('data.data'))->pluck('id')->all());
    }

    public function test_hides_unpublished_and_unapproved_courses(): void
    {
        $this->publishedCourse(['status' => 'draft']);
        Course::factory()->pendingApproval()->create(['status' => 'published', 'classification' => 'skills_professional']);
        $visible = $this->publishedCourse();

        $ids = collect($this->getJson('/api/courses')->json('data.data'))->pluck('id');

        $this->assertEquals([(string) $visible->id], $ids->all());
    }

    public function test_from_price_is_cheapest_upcoming_cohort_and_licence_total_is_summed(): void
    {
        $course = $this->publishedCourse(['price' => 500]);
        Cohort::factory()->physical('Nairobi', 'Kenya')->create(['course_id' => $course->id, 'price' => 650, 'start_date' => now()->addMonth()]);
        Cohort::factory()->create(['course_id' => $course->id, 'price' => 420, 'start_date' => now()->addMonth()]);
        // Past cohorts don't set the price.
        Cohort::factory()->create(['course_id' => $course->id, 'price' => 100, 'start_date' => now()->subMonth()]);

        $ansys = Tool::factory()->create(['licence_price' => 300]);
        $matlab = Tool::factory()->create(['licence_price' => 200]);
        CourseTool::factory()->create(['course_id' => $course->id, 'tool_id' => $ansys->id, 'licence_price' => 250]);
        CourseTool::factory()->create(['course_id' => $course->id, 'tool_id' => $matlab->id]);

        $row = $this->getJson('/api/courses')->json('data.data.0');

        $this->assertSame(420, $row['from_price']);
        $this->assertSame(450, $row['licence_total']); // 250 (course override) + 200 (catalogue price)
        $this->assertSame(1, $row['physical_cohorts_count']);
        $this->assertSame(1, $row['virtual_cohorts_count']);
        $this->assertArrayNotHasKey('full_description', $row);
    }

    public function test_filters_by_mode_location_and_licences(): void
    {
        $nairobi = $this->publishedCourse(['title' => 'A']);
        Cohort::factory()->physical('Nairobi', 'Kenya')->create(['course_id' => $nairobi->id, 'start_date' => now()->addWeek()]);
        $virtual = $this->publishedCourse(['title' => 'B']);
        Cohort::factory()->create(['course_id' => $virtual->id, 'start_date' => now()->addWeek()]);
        CourseTool::factory()->create(['course_id' => $virtual->id]);

        $ids = fn (string $qs) => collect($this->getJson("/api/courses?$qs")->json('data.data'))->pluck('id')->all();

        $this->assertSame([(string) $nairobi->id], $ids('mode=physical'));
        $this->assertSame([(string) $nairobi->id], $ids('country=Kenya&city=Nairobi'));
        $this->assertSame([(string) $virtual->id], $ids('mode=virtual'));
        $this->assertSame([(string) $virtual->id], $ids('licences=with'));
        $this->assertSame([(string) $nairobi->id], $ids('licences=without'));
    }

    public function test_facets_count_each_group_without_its_own_selection(): void
    {
        $maths = Category::factory()->create(['slug' => 'maths', 'name' => 'Mathematics', 'classification' => 'a_level']);
        $physics = Category::factory()->create(['slug' => 'physics', 'name' => 'Physics', 'classification' => 'a_level']);
        $a = $this->publishedCourse(['classification' => 'a_level', 'category_id' => $maths->id]);
        $this->publishedCourse(['classification' => 'a_level', 'category_id' => $physics->id]);
        $this->publishedCourse(['classification' => 'o_level']);
        Cohort::factory()->physical('Kampala', 'Uganda')->create(['course_id' => $a->id, 'start_date' => now()->addWeek()]);

        $facets = $this->getJson('/api/courses/facets?classification=a_level&category=maths')->json('data');

        $this->assertSame(['o_level' => 1, 'a_level' => 2, 'skills_professional' => 0], $facets['classifications']);
        $this->assertEqualsCanonicalizing(['maths', 'physics'], collect($facets['categories'])->pluck('slug')->all());
        $this->assertSame(1, $facets['modes']['physical']);
        $this->assertSame('Uganda', $facets['locations'][0]['country']);
        $this->assertSame('Kampala', $facets['locations'][0]['cities'][0]['city']);
    }

    public function test_course_category_must_belong_to_its_level(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        $oLevelSubject = Category::factory()->create(['classification' => 'o_level']);
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/courses', [
            'title' => 'Excel for accountants',
            'code' => 'XL-1',
            'slug' => 'excel-for-accountants',
            'price' => 100,
            'classification' => 'skills_professional',
            'category_id' => $oLevelSubject->id,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('category_id');
    }

    public function test_admin_sets_course_tools_with_optional_price_override(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        $course = $this->publishedCourse();
        $ansys = Tool::factory()->create(['licence_price' => 300]);
        $qgis = Tool::factory()->create(['licence_price' => 0]);
        Sanctum::actingAs($admin);

        $this->putJson("/api/courses/{$course->id}/tools", ['tools' => [
            ['tool_id' => $ansys->id, 'licence_price' => 180],
            ['tool_id' => $qgis->id],
        ]])->assertStatus(200)->assertJsonPath('data.licence_total', 180);

        // Re-syncing replaces the set.
        $this->putJson("/api/courses/{$course->id}/tools", ['tools' => [['tool_id' => $qgis->id]]])
            ->assertStatus(200)
            ->assertJsonPath('data.licence_total', 0);
        $this->assertDatabaseMissing('course_tools', ['course_id' => $course->id, 'tool_id' => $ansys->id]);
    }

    public function test_non_admin_cannot_set_course_tools(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        $course = $this->publishedCourse();
        Sanctum::actingAs($instructor);

        $this->putJson("/api/courses/{$course->id}/tools", ['tools' => []])->assertStatus(403);
    }

    public function test_enrollment_quotes_cohort_fee_with_or_without_licences(): void
    {
        $course = $this->publishedCourse(['price' => 500]);
        $cohort = Cohort::factory()->physical()->create(['course_id' => $course->id, 'price' => 650, 'start_date' => now()->addWeek(), 'status' => 'open']);
        CourseTool::factory()->create(['course_id' => $course->id, 'licence_price' => 120]);

        $withLicences = User::factory()->create();
        ScholarUser::factory()->create(['id' => $withLicences->id, 'role' => 'student']);
        Sanctum::actingAs($withLicences);
        $this->postJson('/api/enrollments', [
            'learner_id' => $withLicences->id,
            'course_id' => $course->id,
            'cohort_id' => $cohort->id,
            'with_licences' => true,
            'quoted_fee' => 1, // ignored - the server prices it
        ])->assertStatus(201)
            ->assertJsonPath('data.with_licences', true)
            ->assertJsonPath('data.quoted_fee', 770);

        $without = User::factory()->create();
        ScholarUser::factory()->create(['id' => $without->id, 'role' => 'student']);
        Sanctum::actingAs($without);
        $this->postJson('/api/enrollments', [
            'learner_id' => $without->id,
            'course_id' => $course->id,
            'cohort_id' => $cohort->id,
        ])->assertStatus(201)
            ->assertJsonPath('data.with_licences', false)
            ->assertJsonPath('data.quoted_fee', 650);
    }

    public function test_physical_cohort_requires_city_and_country(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        $course = $this->publishedCourse();
        Sanctum::actingAs($admin);

        $this->postJson('/api/cohorts', [
            'course_id' => $course->id,
            'label' => 'Nairobi intake',
            'start_date' => now()->addMonth()->toDateString(),
            'capacity' => 20,
            'mode' => 'physical',
            'price' => 600,
        ])->assertStatus(422)->assertJsonValidationErrors(['location_city', 'location_country']);
    }

    public function test_compact_listing_returns_titles_for_a_subject(): void
    {
        $physics = Category::factory()->create(['slug' => 'a-level-physics', 'classification' => 'a_level']);
        $this->publishedCourse(['classification' => 'a_level', 'category_id' => $physics->id, 'title' => 'Waves']);
        $this->publishedCourse(['classification' => 'a_level', 'category_id' => $physics->id, 'title' => 'Mechanics']);
        $this->publishedCourse(['classification' => 'a_level', 'title' => 'Elsewhere']);

        $rows = $this->getJson('/api/courses?compact=1&category=a-level-physics')->json('data.data');

        $this->assertSame(['Mechanics', 'Waves'], array_column($rows, 'title'));
        $this->assertSame(['id', 'code', 'title'], array_keys($rows[0]));
    }

}

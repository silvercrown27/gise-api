<?php

namespace Tests\Feature;

use App\Models\Cohort;
use App\Models\Course;
use App\Models\ScholarUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CohortControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_is_public(): void
    {
        Cohort::factory()->count(2)->create();

        $response = $this->getJson('/api/cohorts');

        $response->assertStatus(200)->assertJsonStructure(['status', 'data']);
    }

    public function test_show_is_public(): void
    {
        $cohort = Cohort::factory()->create();

        $response = $this->getJson("/api/cohorts/{$cohort->id}");

        $response->assertStatus(200)->assertJsonPath('data.id', (string) $cohort->id);
    }

    public function test_show_returns_404_for_missing_cohort(): void
    {
        $response = $this->getJson('/api/cohorts/' . fake()->uuid());

        $response->assertStatus(404);
    }

    public function test_store_requires_authentication(): void
    {
        $course = Course::factory()->create();

        $response = $this->postJson('/api/cohorts', [
            'course_id' => $course->id,
            'label' => 'Fall 2026',
            'start_date' => now()->addMonth()->format('Y-m-d'),
            'capacity' => 30,
        ]);

        $response->assertStatus(401);
    }

    public function test_store_as_instructor_succeeds(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $instructor->id]);
        Sanctum::actingAs($instructor);

        $response = $this->postJson('/api/cohorts', [
            'course_id' => $course->id,
            'label' => 'Fall 2026',
            'start_date' => now()->addMonth()->format('Y-m-d'),
            'capacity' => 30,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('cohorts', ['course_id' => $course->id, 'label' => 'Fall 2026']);
    }

    public function test_store_validation_failure_returns_422(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'admin']);
        Sanctum::actingAs($instructor);

        $response = $this->postJson('/api/cohorts', []);

        // Role check now passes (admin correctly recognized), so this hits
        // validation, which fails on the required course_id/label/start_date/capacity.
        $response->assertStatus(422);
    }

    public function test_update_requires_authentication(): void
    {
        $cohort = Cohort::factory()->create();

        $response = $this->patchJson("/api/cohorts/{$cohort->id}", ['label' => 'X']);

        $response->assertStatus(401);
    }

    public function test_update_as_admin_succeeds(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        $cohort = Cohort::factory()->create();
        Sanctum::actingAs($admin);

        $response = $this->patchJson("/api/cohorts/{$cohort->id}", [
            'course_id' => $cohort->course_id,
            'label' => 'Updated',
            'start_date' => $cohort->start_date->format('Y-m-d'),
            'capacity' => $cohort->capacity,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.label', 'Updated');
        $this->assertDatabaseHas('cohorts', ['id' => $cohort->id, 'label' => 'Updated']);
    }

    public function test_delete_requires_authentication(): void
    {
        $cohort = Cohort::factory()->create();

        $response = $this->deleteJson("/api/cohorts/{$cohort->id}");

        $response->assertStatus(401);
    }

    public function test_delete_as_admin_succeeds(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        $cohort = Cohort::factory()->create();
        Sanctum::actingAs($admin);

        $response = $this->deleteJson("/api/cohorts/{$cohort->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('cohorts', ['id' => $cohort->id]);
    }
}

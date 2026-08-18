<?php

namespace Tests\Feature;

use App\Models\CertificationLevel;
use App\Models\CertificationPace;
use App\Models\ScholarUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CertificationPaceControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_is_public(): void
    {
        CertificationPace::factory()->count(2)->create();

        $response = $this->getJson('/api/certification-paces');

        $response->assertStatus(200)->assertJsonStructure(['status', 'data']);
    }

    public function test_index_filters_by_certification_level_id(): void
    {
        $level = CertificationLevel::factory()->create();
        $matching = CertificationPace::factory()->create(['certification_level_id' => $level->id]);
        CertificationPace::factory()->create(); // different level

        $response = $this->getJson("/api/certification-paces?certification_level_id={$level->id}");

        $response->assertStatus(200);
        $ids = collect($response->json('data.data'))->pluck('id');
        $this->assertCount(1, $ids);
        $this->assertTrue($ids->contains((string) $matching->id));
    }

    public function test_show_is_public(): void
    {
        $pace = CertificationPace::factory()->create(['name' => 'Full certification']);

        $response = $this->getJson("/api/certification-paces/{$pace->id}");

        $response->assertStatus(200)->assertJsonPath('data.name', 'Full certification');
    }

    public function test_store_requires_authentication(): void
    {
        $level = CertificationLevel::factory()->create();

        $response = $this->postJson('/api/certification-paces', [
            'certification_level_id' => $level->id,
            'name' => 'Full certification',
            'certification_track' => 'full',
            'duration_weeks' => 52,
        ]);

        $response->assertStatus(401);
    }

    public function test_store_as_instructor_succeeds(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        $level = CertificationLevel::factory()->create();
        Sanctum::actingAs($instructor);

        $response = $this->postJson('/api/certification-paces', [
            'certification_level_id' => $level->id,
            'name' => 'Full certification',
            'certification_track' => 'full',
            'duration_weeks' => 52,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('certification_paces', [
            'certification_level_id' => $level->id,
            'certification_track' => 'full',
        ]);
    }

    public function test_store_as_student_is_forbidden(): void
    {
        $student = User::factory()->create();
        ScholarUser::factory()->create(['id' => $student->id, 'role' => 'student']);
        $level = CertificationLevel::factory()->create();
        Sanctum::actingAs($student);

        $response = $this->postJson('/api/certification-paces', [
            'certification_level_id' => $level->id,
            'name' => 'Full certification',
            'certification_track' => 'full',
            'duration_weeks' => 52,
        ]);

        $response->assertStatus(403);
    }

    public function test_store_rejects_invalid_certification_track(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        $level = CertificationLevel::factory()->create();
        Sanctum::actingAs($instructor);

        $response = $this->postJson('/api/certification-paces', [
            'certification_level_id' => $level->id,
            'name' => 'Custom pace',
            'certification_track' => 'half',
            'duration_weeks' => 52,
        ]);

        $response->assertStatus(422);
    }

    public function test_update_as_instructor_succeeds(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        $pace = CertificationPace::factory()->create(['duration_weeks' => 40]);
        Sanctum::actingAs($instructor);

        $response = $this->patchJson("/api/certification-paces/{$pace->id}", [
            'certification_level_id' => $pace->certification_level_id,
            'name' => $pace->name,
            'certification_track' => $pace->certification_track,
            'duration_weeks' => 45,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.duration_weeks', 45);
    }

    public function test_delete_as_instructor_succeeds(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        $pace = CertificationPace::factory()->create();
        Sanctum::actingAs($instructor);

        $response = $this->deleteJson("/api/certification-paces/{$pace->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('certification_paces', ['id' => $pace->id]);
    }
}

<?php

namespace Tests\Feature;

use App\Models\CertificationLevel;
use App\Models\CertificationType;
use App\Models\ScholarUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CertificationLevelControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_is_public(): void
    {
        CertificationLevel::factory()->count(2)->create();

        $response = $this->getJson('/api/certification-levels');

        $response->assertStatus(200)->assertJsonStructure(['status', 'data']);
    }

    public function test_index_filters_by_certification_type_id(): void
    {
        $type = CertificationType::factory()->create();
        $matching = CertificationLevel::factory()->create(['certification_type_id' => $type->id]);
        CertificationLevel::factory()->create(); // different type

        $response = $this->getJson("/api/certification-levels?certification_type_id={$type->id}");

        $response->assertStatus(200);
        $ids = collect($response->json('data.data'))->pluck('id');
        $this->assertCount(1, $ids);
        $this->assertTrue($ids->contains((string) $matching->id));
    }

    public function test_show_is_public(): void
    {
        $level = CertificationLevel::factory()->create(['name' => 'O Level']);

        $response = $this->getJson("/api/certification-levels/{$level->id}");

        $response->assertStatus(200)->assertJsonPath('data.name', 'O Level');
    }

    public function test_store_requires_authentication(): void
    {
        $type = CertificationType::factory()->create();

        $response = $this->postJson('/api/certification-levels', [
            'certification_type_id' => $type->id,
            'name' => 'O Level',
            'slug' => 'o-level',
        ]);

        $response->assertStatus(401);
    }

    public function test_store_as_instructor_succeeds(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        $type = CertificationType::factory()->create();
        Sanctum::actingAs($instructor);

        $response = $this->postJson('/api/certification-levels', [
            'certification_type_id' => $type->id,
            'name' => 'O Level',
            'slug' => 'o-level',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('certification_levels', ['slug' => 'o-level', 'certification_type_id' => $type->id]);
    }

    public function test_store_as_student_is_forbidden(): void
    {
        $student = User::factory()->create();
        ScholarUser::factory()->create(['id' => $student->id, 'role' => 'student']);
        $type = CertificationType::factory()->create();
        Sanctum::actingAs($student);

        $response = $this->postJson('/api/certification-levels', [
            'certification_type_id' => $type->id,
            'name' => 'O Level',
            'slug' => 'o-level',
        ]);

        $response->assertStatus(403);
    }

    public function test_store_rejects_invalid_certification_type(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        Sanctum::actingAs($instructor);

        $response = $this->postJson('/api/certification-levels', [
            'certification_type_id' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'O Level',
            'slug' => 'o-level',
        ]);

        $response->assertStatus(422);
    }

    public function test_store_allows_same_slug_under_different_certification_types(): void
    {
        $typeA = CertificationType::factory()->create();
        $typeB = CertificationType::factory()->create();
        CertificationLevel::factory()->create(['certification_type_id' => $typeA->id, 'slug' => 'level-one']);

        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        Sanctum::actingAs($instructor);

        $response = $this->postJson('/api/certification-levels', [
            'certification_type_id' => $typeB->id,
            'name' => 'Level One',
            'slug' => 'level-one',
        ]);

        $response->assertStatus(201);
    }

    public function test_update_as_instructor_succeeds(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        $level = CertificationLevel::factory()->create(['name' => 'O Level']);
        Sanctum::actingAs($instructor);

        $response = $this->patchJson("/api/certification-levels/{$level->id}", [
            'certification_type_id' => $level->certification_type_id,
            'name' => 'O Level Updated',
            'slug' => $level->slug,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.name', 'O Level Updated');
    }

    public function test_delete_as_instructor_succeeds(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        $level = CertificationLevel::factory()->create();
        Sanctum::actingAs($instructor);

        $response = $this->deleteJson("/api/certification-levels/{$level->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('certification_levels', ['id' => $level->id]);
    }
}

<?php

namespace Tests\Feature;

use App\Models\CertificationType;
use App\Models\ScholarUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CertificationTypeControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_is_public(): void
    {
        CertificationType::factory()->count(2)->create();

        $response = $this->getJson('/api/certification-types');

        $response->assertStatus(200)->assertJsonStructure(['status', 'data']);
    }

    public function test_show_is_public(): void
    {
        $type = CertificationType::factory()->create(['name' => 'IGCSE']);

        $response = $this->getJson("/api/certification-types/{$type->id}");

        $response->assertStatus(200)->assertJsonPath('data.name', 'IGCSE');
    }

    public function test_store_requires_authentication(): void
    {
        $response = $this->postJson('/api/certification-types', [
            'name' => 'IGCSE',
            'slug' => 'igcse',
        ]);

        $response->assertStatus(401);
    }

    public function test_store_as_instructor_succeeds(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        Sanctum::actingAs($instructor);

        $response = $this->postJson('/api/certification-types', [
            'name' => 'IGCSE',
            'slug' => 'igcse',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('certification_types', ['slug' => 'igcse']);
    }

    public function test_store_as_student_is_forbidden(): void
    {
        $student = User::factory()->create();
        ScholarUser::factory()->create(['id' => $student->id, 'role' => 'student']);
        Sanctum::actingAs($student);

        $response = $this->postJson('/api/certification-types', [
            'name' => 'IGCSE',
            'slug' => 'igcse',
        ]);

        $response->assertStatus(403);
    }

    public function test_store_rejects_duplicate_slug(): void
    {
        CertificationType::factory()->create(['slug' => 'igcse']);
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        Sanctum::actingAs($instructor);

        $response = $this->postJson('/api/certification-types', [
            'name' => 'IGCSE Duplicate',
            'slug' => 'igcse',
        ]);

        $response->assertStatus(422);
    }

    public function test_update_as_instructor_succeeds(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        $type = CertificationType::factory()->create(['name' => 'IGCSE']);
        Sanctum::actingAs($instructor);

        $response = $this->patchJson("/api/certification-types/{$type->id}", [
            'name' => 'IGCSE Updated',
            'slug' => $type->slug,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.name', 'IGCSE Updated');
    }

    public function test_delete_as_instructor_succeeds(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        $type = CertificationType::factory()->create();
        Sanctum::actingAs($instructor);

        $response = $this->deleteJson("/api/certification-types/{$type->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('certification_types', ['id' => $type->id]);
    }

    public function test_delete_as_student_is_forbidden(): void
    {
        $student = User::factory()->create();
        ScholarUser::factory()->create(['id' => $student->id, 'role' => 'student']);
        $type = CertificationType::factory()->create();
        Sanctum::actingAs($student);

        $response = $this->deleteJson("/api/certification-types/{$type->id}");

        $response->assertStatus(403);
    }
}

<?php

namespace Tests\Feature;

use App\Models\PlatformStat;
use App\Models\ScholarUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlatformStatControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_is_public(): void
    {
        PlatformStat::factory()->count(2)->create();

        $response = $this->getJson('/api/platform-stats');

        $response->assertStatus(200)->assertJsonStructure(['status', 'data']);
    }

    public function test_show_is_public(): void
    {
        $stat = PlatformStat::factory()->create();

        $response = $this->getJson("/api/platform-stats/{$stat->id}");

        $response->assertStatus(200)->assertJsonPath('data.id', (string) $stat->id);
    }

    public function test_show_returns_404_for_missing_stat(): void
    {
        $response = $this->getJson('/api/platform-stats/' . fake()->uuid());

        $response->assertStatus(404);
    }

    public function test_store_requires_authentication(): void
    {
        $response = $this->postJson('/api/platform-stats', [
            'label' => 'Learners',
            'value' => '10000',
        ]);

        $response->assertStatus(401);
    }

    public function test_store_as_admin_succeeds(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/platform-stats', [
            'label' => 'Learners',
            'value' => '10000',
        ]);

        $response->assertStatus(201);
    }

    public function test_store_as_student_is_forbidden(): void
    {
        $student = User::factory()->create();
        ScholarUser::factory()->create(['id' => $student->id, 'role' => 'student']);
        Sanctum::actingAs($student);

        $response = $this->postJson('/api/platform-stats', [
            'label' => 'Learners',
            'value' => '10000',
        ]);

        $response->assertStatus(403);
    }

    public function test_update_requires_authentication(): void
    {
        $stat = PlatformStat::factory()->create();

        $response = $this->patchJson("/api/platform-stats/{$stat->id}", ['label' => 'X']);

        $response->assertStatus(401);
    }

    public function test_update_as_admin_succeeds(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        $stat = PlatformStat::factory()->create();
        Sanctum::actingAs($admin);

        $response = $this->patchJson("/api/platform-stats/{$stat->id}", [
            'label' => 'Updated',
            'value' => $stat->value,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.label', 'Updated');
    }

    public function test_update_as_student_is_forbidden(): void
    {
        $student = User::factory()->create();
        ScholarUser::factory()->create(['id' => $student->id, 'role' => 'student']);
        $stat = PlatformStat::factory()->create();
        Sanctum::actingAs($student);

        $response = $this->patchJson("/api/platform-stats/{$stat->id}", [
            'label' => 'Updated',
            'value' => $stat->value,
        ]);

        $response->assertStatus(403);
    }

    public function test_delete_requires_authentication(): void
    {
        $stat = PlatformStat::factory()->create();

        $response = $this->deleteJson("/api/platform-stats/{$stat->id}");

        $response->assertStatus(401);
    }

    public function test_delete_as_admin_succeeds(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        $stat = PlatformStat::factory()->create();
        Sanctum::actingAs($admin);

        $response = $this->deleteJson("/api/platform-stats/{$stat->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('platform_stats', ['id' => $stat->id]);
    }

    public function test_delete_as_student_is_forbidden(): void
    {
        $student = User::factory()->create();
        ScholarUser::factory()->create(['id' => $student->id, 'role' => 'student']);
        $stat = PlatformStat::factory()->create();
        Sanctum::actingAs($student);

        $response = $this->deleteJson("/api/platform-stats/{$stat->id}");

        $response->assertStatus(403);
    }
}

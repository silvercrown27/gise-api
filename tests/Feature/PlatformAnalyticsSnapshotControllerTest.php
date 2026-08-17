<?php

namespace Tests\Feature;

use App\Models\PlatformAnalyticsSnapshot;
use App\Models\ScholarUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlatformAnalyticsSnapshotControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_requires_authentication(): void
    {
        $response = $this->getJson('/api/platform-analytics-snapshots');

        $response->assertStatus(401);
    }

    public function test_index_as_admin_succeeds(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        PlatformAnalyticsSnapshot::factory()->count(2)->create();
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/platform-analytics-snapshots');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data.data'));
    }

    public function test_store_requires_authentication(): void
    {
        $response = $this->postJson('/api/platform-analytics-snapshots', [
            'snapshot_date' => now()->format('Y-m-d'),
        ]);

        $response->assertStatus(401);
    }

    public function test_store_as_admin_succeeds(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/platform-analytics-snapshots', [
            'snapshot_date' => now()->format('Y-m-d'),
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.snapshot_date', fn ($value) => str_starts_with($value, now()->format('Y-m-d')));
    }

    public function test_show_requires_authentication(): void
    {
        $snapshot = PlatformAnalyticsSnapshot::factory()->create();

        $response = $this->getJson("/api/platform-analytics-snapshots/{$snapshot->id}");

        $response->assertStatus(401);
    }

    public function test_show_as_admin_succeeds(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        $snapshot = PlatformAnalyticsSnapshot::factory()->create();
        Sanctum::actingAs($admin);

        $response = $this->getJson("/api/platform-analytics-snapshots/{$snapshot->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', (string) $snapshot->id);
    }

    public function test_update_requires_authentication(): void
    {
        $snapshot = PlatformAnalyticsSnapshot::factory()->create();

        $response = $this->patchJson("/api/platform-analytics-snapshots/{$snapshot->id}", [
            'snapshot_date' => $snapshot->snapshot_date->format('Y-m-d'),
            'total_learners' => 500,
        ]);

        $response->assertStatus(401);
    }

    public function test_update_as_admin_succeeds(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        $snapshot = PlatformAnalyticsSnapshot::factory()->create();
        Sanctum::actingAs($admin);

        $response = $this->patchJson("/api/platform-analytics-snapshots/{$snapshot->id}", [
            'snapshot_date' => $snapshot->snapshot_date->format('Y-m-d'),
            'total_learners' => 500,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.total_learners', 500);
        $this->assertDatabaseHas('platform_analytics_snapshots', ['id' => $snapshot->id, 'total_learners' => 500]);
    }

    public function test_delete_requires_authentication(): void
    {
        $snapshot = PlatformAnalyticsSnapshot::factory()->create();

        $response = $this->deleteJson("/api/platform-analytics-snapshots/{$snapshot->id}");

        $response->assertStatus(401);
    }

    public function test_delete_as_admin_succeeds(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        $snapshot = PlatformAnalyticsSnapshot::factory()->create();
        Sanctum::actingAs($admin);

        $response = $this->deleteJson("/api/platform-analytics-snapshots/{$snapshot->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('platform_analytics_snapshots', ['id' => $snapshot->id]);
    }
}

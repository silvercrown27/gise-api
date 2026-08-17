<?php

namespace Tests\Feature;

use App\Models\ScholarUser;
use App\Models\SiteUpdate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SiteUpdateControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_requires_authentication(): void
    {
        $response = $this->getJson('/api/site-updates');

        $response->assertStatus(401);
    }

    public function test_index_as_admin_succeeds(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        SiteUpdate::factory()->count(2)->create();
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/site-updates');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data.data'));
    }

    public function test_store_requires_authentication(): void
    {
        $response = $this->postJson('/api/site-updates', [
            'type' => 'signup',
            'title' => 'New signup',
        ]);

        $response->assertStatus(401);
    }

    public function test_store_as_admin_succeeds(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/site-updates', [
            'type' => 'signup',
            'title' => 'New signup',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('site_updates', ['type' => 'signup', 'title' => 'New signup']);
    }

    public function test_show_requires_authentication(): void
    {
        $update = SiteUpdate::factory()->create();

        $response = $this->getJson("/api/site-updates/{$update->id}");

        $response->assertStatus(401);
    }

    public function test_show_as_admin_succeeds(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        $update = SiteUpdate::factory()->create();
        Sanctum::actingAs($admin);

        $response = $this->getJson("/api/site-updates/{$update->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', (string) $update->id);
    }

    public function test_update_requires_authentication(): void
    {
        $update = SiteUpdate::factory()->create();

        $response = $this->patchJson("/api/site-updates/{$update->id}", [
            'type' => 'signup',
            'title' => 'Updated title',
        ]);

        $response->assertStatus(401);
    }

    public function test_update_as_admin_succeeds(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        $update = SiteUpdate::factory()->create();
        Sanctum::actingAs($admin);

        $response = $this->patchJson("/api/site-updates/{$update->id}", [
            'type' => 'signup',
            'title' => 'Updated title',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.title', 'Updated title');
        $this->assertDatabaseHas('site_updates', ['id' => $update->id, 'title' => 'Updated title']);
    }

    public function test_delete_requires_authentication(): void
    {
        $update = SiteUpdate::factory()->create();

        $response = $this->deleteJson("/api/site-updates/{$update->id}");

        $response->assertStatus(401);
    }

    public function test_delete_as_admin_succeeds(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        $update = SiteUpdate::factory()->create();
        Sanctum::actingAs($admin);

        $response = $this->deleteJson("/api/site-updates/{$update->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('site_updates', ['id' => $update->id]);
    }
}

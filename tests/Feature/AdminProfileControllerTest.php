<?php

namespace Tests\Feature;

use App\Models\AdminProfile;
use App\Models\ScholarUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminProfileControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_requires_authentication(): void
    {
        $response = $this->getJson('/api/admin-profiles');

        $response->assertStatus(401);
    }

    public function test_index_as_admin_is_forbidden_due_to_lookup_bug(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['user_id' => $admin->id, 'role' => 'admin']);
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin-profiles');

        $response->assertStatus(403);
    }

    public function test_store_requires_authentication(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson('/api/admin-profiles', [
            'user_id' => $user->id,
            'permission_level' => 'support_admin',
        ]);

        $response->assertStatus(401);
    }

    public function test_store_as_admin_is_forbidden_due_to_lookup_bug(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['user_id' => $admin->id, 'role' => 'admin']);
        $newUser = User::factory()->create();
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/admin-profiles', [
            'user_id' => $newUser->id,
            'permission_level' => 'support_admin',
        ]);

        $response->assertStatus(403);
    }

    public function test_show_requires_authentication(): void
    {
        $profile = AdminProfile::factory()->create();

        $response = $this->getJson("/api/admin-profiles/{$profile->id}");

        $response->assertStatus(401);
    }

    public function test_show_as_admin_is_forbidden_due_to_lookup_bug(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['user_id' => $admin->id, 'role' => 'admin']);
        $profile = AdminProfile::factory()->create();
        Sanctum::actingAs($admin);

        $response = $this->getJson("/api/admin-profiles/{$profile->id}");

        $response->assertStatus(403);
    }

    public function test_update_requires_authentication(): void
    {
        $profile = AdminProfile::factory()->create();

        $response = $this->patchJson("/api/admin-profiles/{$profile->id}", [
            'user_id' => $profile->user_id,
            'permission_level' => 'super_admin',
        ]);

        $response->assertStatus(401);
    }

    public function test_update_as_admin_is_forbidden_due_to_lookup_bug(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['user_id' => $admin->id, 'role' => 'admin']);
        $profile = AdminProfile::factory()->create();
        Sanctum::actingAs($admin);

        $response = $this->patchJson("/api/admin-profiles/{$profile->id}", [
            'user_id' => $profile->user_id,
            'permission_level' => 'super_admin',
        ]);

        $response->assertStatus(403);
    }

    public function test_delete_requires_authentication(): void
    {
        $profile = AdminProfile::factory()->create();

        $response = $this->deleteJson("/api/admin-profiles/{$profile->id}");

        $response->assertStatus(401);
    }

    public function test_delete_as_admin_is_forbidden_due_to_lookup_bug(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['user_id' => $admin->id, 'role' => 'admin']);
        $profile = AdminProfile::factory()->create();
        Sanctum::actingAs($admin);

        $response = $this->deleteJson("/api/admin-profiles/{$profile->id}");

        $response->assertStatus(403);
    }
}

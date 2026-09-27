<?php

namespace Tests\Feature;

use App\Models\ScholarUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ScholarUserControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_requires_authentication(): void
    {
        $response = $this->getJson('/api/scholar-users');

        $response->assertStatus(401);
    }

    public function test_index_as_authenticated_user_scopes_to_own_row(): void
    {
        // index() scopes non-admins to ScholarUser::where('id', $request->user()->id).
        // Since scholar_users.id shares the same value as the caller's users.id, this
        // correctly returns only the caller's own row.
        $user = User::factory()->create();
        $scholarUser = ScholarUser::factory()->create(['id' => $user->id, 'role' => 'student']);
        ScholarUser::factory()->create(); // someone else's row
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/scholar-users');

        $response->assertStatus(200);
        $ids = collect($response->json('data.data'))->pluck('id');
        $this->assertTrue($ids->contains((string) $scholarUser->id));
        $this->assertCount(1, $ids);
    }

    public function test_store_requires_authentication(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson('/api/scholar-users', [
            'id' => $user->id,
            'email' => $user->email,
            'role' => 'student',
        ]);

        $response->assertStatus(401);
    }

    public function test_store_as_admin_succeeds(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        $newUser = User::factory()->create();
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/scholar-users', [
            'id' => $newUser->id,
            'email' => $newUser->email,
            'role' => 'student',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.id', (string) $newUser->id);
        $this->assertDatabaseHas('scholar_users', ['id' => $newUser->id, 'role' => 'student']);
    }

    public function test_store_as_non_admin_is_forbidden(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        $newUser = User::factory()->create();
        Sanctum::actingAs($instructor);

        $response = $this->postJson('/api/scholar-users', [
            'id' => $newUser->id,
            'email' => $newUser->email,
            'role' => 'student',
        ]);

        $response->assertStatus(403);
    }

    public function test_store_rejects_id_not_belonging_to_an_existing_user(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/scholar-users', [
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'email' => fake()->unique()->safeEmail(),
            'role' => 'student',
        ]);

        $response->assertStatus(422);
    }

    public function test_store_rejects_id_already_used_by_another_scholar_user(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        $existing = ScholarUser::factory()->create();
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/scholar-users', [
            'id' => $existing->id,
            'email' => fake()->unique()->safeEmail(),
            'role' => 'student',
        ]);

        $response->assertStatus(422);
    }

    public function test_show_requires_authentication(): void
    {
        $scholarUser = ScholarUser::factory()->create();

        $response = $this->getJson("/api/scholar-users/{$scholarUser->id}");

        $response->assertStatus(401);
    }

    public function test_show_returns_404_for_missing_scholar_user(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/scholar-users/' . fake()->uuid());

        $response->assertStatus(404);
    }

    public function test_show_own_scholar_user_succeeds(): void
    {
        $user = User::factory()->create();
        $scholarUser = ScholarUser::factory()->create(['id' => $user->id]);
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/scholar-users/{$scholarUser->id}");

        $response->assertStatus(200);
    }

    public function test_show_other_users_scholar_user_is_forbidden(): void
    {
        $user = User::factory()->create();
        $scholarUser = ScholarUser::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/scholar-users/{$scholarUser->id}");

        $response->assertStatus(403);
    }

    public function test_update_requires_authentication(): void
    {
        $scholarUser = ScholarUser::factory()->create();

        $response = $this->patchJson("/api/scholar-users/{$scholarUser->id}", [
            'role' => 'student',
        ]);

        $response->assertStatus(401);
    }

    public function test_update_own_scholar_user_strips_role_and_cannot_self_promote(): void
    {
        // $isSelf compares scholar_users.id directly against the caller's users.id
        // (the same value under the shared-identity design), so self-edit is
        // reachable -- but 'role' is explicitly stripped from the payload for
        // non-admin callers, so a student cannot self-promote to admin even though
        // they can successfully edit their own row (e.g. phone/avatar_url).
        $user = User::factory()->create();
        $scholarUser = ScholarUser::factory()->create(['id' => $user->id, 'role' => 'student']);
        Sanctum::actingAs($user);

        $response = $this->patchJson("/api/scholar-users/{$scholarUser->id}", [
            'role' => 'admin',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.role', 'student');
        $this->assertDatabaseHas('scholar_users', ['id' => $scholarUser->id, 'role' => 'student']);
    }

    public function test_profile_update_never_changes_role(): void
    {
        // Roles only change through PATCH /scholar-users/{id}/role (super admins).
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'super_admin']);
        $target = ScholarUser::factory()->create(['role' => 'student']);
        Sanctum::actingAs($admin);

        $this->patchJson("/api/scholar-users/{$target->id}", ['role' => 'admin', 'phone' => '0712000000'])
            ->assertStatus(200)
            ->assertJsonPath('data.role', 'student');
    }

    public function test_update_validation_failure_returns_422(): void
    {
        $scholarUser = ScholarUser::factory()->create();
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->patchJson("/api/scholar-users/{$scholarUser->id}", [
            'status' => 'not-a-real-status',
        ]);

        $response->assertStatus(422);
    }

    public function test_delete_requires_authentication(): void
    {
        $scholarUser = ScholarUser::factory()->create();

        $response = $this->deleteJson("/api/scholar-users/{$scholarUser->id}");

        $response->assertStatus(401);
    }

    public function test_delete_as_admin_succeeds(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        $scholarUser = ScholarUser::factory()->create();
        Sanctum::actingAs($admin);

        $response = $this->deleteJson("/api/scholar-users/{$scholarUser->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('scholar_users', ['id' => $scholarUser->id]);
    }

    public function test_delete_as_non_admin_is_forbidden(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        $scholarUser = ScholarUser::factory()->create();
        Sanctum::actingAs($instructor);

        $response = $this->deleteJson("/api/scholar-users/{$scholarUser->id}");

        $response->assertStatus(403);
    }
}

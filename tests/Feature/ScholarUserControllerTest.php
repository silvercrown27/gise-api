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

    public function test_index_as_authenticated_user_scopes_to_own_row_by_type_mismatched_query(): void
    {
        // index() does NOT hard-reject for non-admins: it queries
        // ScholarUser::where('user_id', $request->user()->id). Because Eloquent query
        // bindings stringify values, this works fine as a WHERE clause (unlike the
        // in-PHP === comparisons elsewhere), so a learner gets their own row(s) back
        // scoped correctly -- this path is NOT affected by the object/string mismatch.
        $user = User::factory()->create();
        $scholarUser = ScholarUser::factory()->create(['user_id' => $user->id, 'role' => 'learner']);
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
            'user_id' => $user->id,
            'role' => 'learner',
        ]);

        $response->assertStatus(401);
    }

    public function test_store_as_admin_is_forbidden_due_to_lookup_bug(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['user_id' => $admin->id, 'role' => 'admin']);
        $newUser = User::factory()->create();
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/scholar-users', [
            'user_id' => $newUser->id,
            'role' => 'learner',
        ]);

        $response->assertStatus(403);
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

    public function test_show_own_scholar_user_is_forbidden_due_to_type_mismatch(): void
    {
        $user = User::factory()->create();
        $scholarUser = ScholarUser::factory()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/scholar-users/{$scholarUser->id}");

        $response->assertStatus(403);
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
            'user_id' => $scholarUser->user_id,
            'role' => 'learner',
        ]);

        $response->assertStatus(401);
    }

    public function test_update_own_scholar_user_is_forbidden_due_to_type_mismatch(): void
    {
        // This is the mass-assignment-relevant endpoint: if $isSelf worked, a learner
        // could PATCH their own scholar_users row including 'role', granting themselves
        // admin. It is currently blocked, but only by the incidental type-mismatch bug,
        // not by any real authorization design (there is no role-change protection here).
        $user = User::factory()->create();
        $scholarUser = ScholarUser::factory()->create(['user_id' => $user->id, 'role' => 'learner']);
        Sanctum::actingAs($user);

        $response = $this->patchJson("/api/scholar-users/{$scholarUser->id}", [
            'user_id' => $user->id,
            'role' => 'admin',
        ]);

        $response->assertStatus(403);
    }

    public function test_update_validation_failure_returns_422_even_before_ownership_check(): void
    {
        $scholarUser = ScholarUser::factory()->create();
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->patchJson("/api/scholar-users/{$scholarUser->id}", []);

        $response->assertStatus(422);
    }

    public function test_delete_requires_authentication(): void
    {
        $scholarUser = ScholarUser::factory()->create();

        $response = $this->deleteJson("/api/scholar-users/{$scholarUser->id}");

        $response->assertStatus(401);
    }

    public function test_delete_as_admin_is_forbidden_due_to_lookup_bug(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['user_id' => $admin->id, 'role' => 'admin']);
        $scholarUser = ScholarUser::factory()->create();
        Sanctum::actingAs($admin);

        $response = $this->deleteJson("/api/scholar-users/{$scholarUser->id}");

        $response->assertStatus(403);
    }
}

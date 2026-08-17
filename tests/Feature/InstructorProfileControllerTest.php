<?php

namespace Tests\Feature;

use App\Models\InstructorProfile;
use App\Models\ScholarUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InstructorProfileControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_requires_authentication(): void
    {
        $response = $this->getJson('/api/instructor-profiles');

        $response->assertStatus(401);
    }

    public function test_index_as_instructor_is_forbidden_due_to_lookup_bug(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        Sanctum::actingAs($instructor);

        $response = $this->getJson('/api/instructor-profiles');

        $response->assertStatus(403);
    }

    public function test_store_requires_authentication(): void
    {
        $instructor = User::factory()->create();

        $response = $this->postJson('/api/instructor-profiles', [
            'user_id' => $instructor->id,
            'bio' => 'Experienced developer',
        ]);

        $response->assertStatus(401);
    }

    public function test_store_as_instructor_is_forbidden_due_to_lookup_bug(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        Sanctum::actingAs($instructor);

        $response = $this->postJson('/api/instructor-profiles', [
            'user_id' => $instructor->id,
            'bio' => 'Experienced developer',
        ]);

        $response->assertStatus(403);
    }

    public function test_show_requires_authentication(): void
    {
        $profile = InstructorProfile::factory()->create();

        $response = $this->getJson("/api/instructor-profiles/{$profile->id}");

        $response->assertStatus(401);
    }

    public function test_show_returns_404_for_missing_profile(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/instructor-profiles/' . fake()->uuid());

        $response->assertStatus(404);
    }

    public function test_show_own_profile_succeeds_and_reveals_payout_details(): void
    {
        // Fixed: show() now casts both sides to string before comparing, so the
        // instructor who owns the profile can view it. payout_details is hidden by
        // default (App\Models\InstructorProfile::$hidden) but explicitly made visible
        // for the owner/admin in the controller.
        $instructor = User::factory()->create();
        $profile = InstructorProfile::factory()->create(['user_id' => $instructor->id, 'payout_details' => 'ACC-12345']);
        Sanctum::actingAs($instructor);

        $response = $this->getJson("/api/instructor-profiles/{$profile->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.payout_details', 'ACC-12345');
    }

    public function test_show_hides_payout_details_from_non_owner(): void
    {
        $profile = InstructorProfile::factory()->create(['payout_details' => 'ACC-SECRET']);

        // No route currently allows a non-owner, non-admin caller through to see this
        // profile at all (they'd get 403), but this test documents the model-level
        // default independent of controller authorization.
        $this->assertArrayNotHasKey('payout_details', $profile->toArray());
    }

    public function test_update_requires_authentication(): void
    {
        $profile = InstructorProfile::factory()->create();

        $response = $this->patchJson("/api/instructor-profiles/{$profile->id}", [
            'user_id' => $profile->user_id,
            'bio' => 'Updated bio',
        ]);

        $response->assertStatus(401);
    }

    public function test_update_own_profile_is_forbidden_due_to_lookup_bug(): void
    {
        // update() gates $isSelf behind $user (ScholarUser::find) being truthy AND
        // role === 'instructor', so unlike show(), this path needs the ScholarUser
        // lookup to succeed at all -- which it never does for a real caller.
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        $profile = InstructorProfile::factory()->create(['user_id' => $instructor->id]);
        Sanctum::actingAs($instructor);

        $response = $this->patchJson("/api/instructor-profiles/{$profile->id}", [
            'user_id' => $instructor->id,
            'bio' => 'Updated bio',
        ]);

        $response->assertStatus(403);
    }

    public function test_delete_requires_authentication(): void
    {
        $profile = InstructorProfile::factory()->create();

        $response = $this->deleteJson("/api/instructor-profiles/{$profile->id}");

        $response->assertStatus(401);
    }

    public function test_delete_as_admin_is_forbidden_due_to_lookup_bug(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        $profile = InstructorProfile::factory()->create();
        Sanctum::actingAs($admin);

        $response = $this->deleteJson("/api/instructor-profiles/{$profile->id}");

        $response->assertStatus(403);
    }
}

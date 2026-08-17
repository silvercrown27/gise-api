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
        ScholarUser::factory()->create(['user_id' => $instructor->id, 'role' => 'instructor']);
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
        ScholarUser::factory()->create(['user_id' => $instructor->id, 'role' => 'instructor']);
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

    public function test_show_own_profile_is_forbidden_due_to_type_mismatch(): void
    {
        $instructor = User::factory()->create();
        $profile = InstructorProfile::factory()->create(['user_id' => $instructor->id]);
        Sanctum::actingAs($instructor);

        $response = $this->getJson("/api/instructor-profiles/{$profile->id}");

        $response->assertStatus(403);
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
        ScholarUser::factory()->create(['user_id' => $instructor->id, 'role' => 'instructor']);
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
        ScholarUser::factory()->create(['user_id' => $admin->id, 'role' => 'admin']);
        $profile = InstructorProfile::factory()->create();
        Sanctum::actingAs($admin);

        $response = $this->deleteJson("/api/instructor-profiles/{$profile->id}");

        $response->assertStatus(403);
    }
}

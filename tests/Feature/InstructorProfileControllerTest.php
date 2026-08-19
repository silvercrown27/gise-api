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

    public function test_index_as_instructor_is_scoped_to_own_profile(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        InstructorProfile::factory()->create(['user_id' => $instructor->id]);
        InstructorProfile::factory()->create(); // someone else's profile
        Sanctum::actingAs($instructor);

        $response = $this->getJson('/api/instructor-profiles');

        $response->assertStatus(200);
        $ids = collect($response->json('data.data'))->pluck('user_id');
        $this->assertCount(1, $ids);
        $this->assertTrue($ids->contains((string) $instructor->id));
    }

    public function test_index_as_admin_includes_user_relation_and_filters_by_approval_status(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        $pending = InstructorProfile::factory()->pending()->create();
        InstructorProfile::factory()->create(); // approved
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/instructor-profiles?approval_status=pending');

        $response->assertStatus(200);
        $ids = collect($response->json('data.data'))->pluck('id');
        $this->assertCount(1, $ids);
        $this->assertTrue($ids->contains((string) $pending->id));
        $response->assertJsonPath('data.data.0.user.id', (string) $pending->user_id);
    }

    public function test_index_as_student_is_forbidden(): void
    {
        $student = User::factory()->create();
        ScholarUser::factory()->create(['id' => $student->id, 'role' => 'student']);
        Sanctum::actingAs($student);

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

    public function test_store_as_instructor_succeeds(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        Sanctum::actingAs($instructor);

        $response = $this->postJson('/api/instructor-profiles', [
            'user_id' => $instructor->id,
            'bio' => 'Experienced developer',
        ]);

        $response->assertStatus(201);
    }

    public function test_store_as_student_is_forbidden(): void
    {
        $student = User::factory()->create();
        ScholarUser::factory()->create(['id' => $student->id, 'role' => 'student']);
        Sanctum::actingAs($student);

        $response = $this->postJson('/api/instructor-profiles', [
            'user_id' => $student->id,
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

    public function test_show_as_admin_includes_user_relation(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        $profile = InstructorProfile::factory()->create();
        Sanctum::actingAs($admin);

        $response = $this->getJson("/api/instructor-profiles/{$profile->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.user.id', (string) $profile->user_id);
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

    public function test_update_own_profile_as_instructor_succeeds(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        $profile = InstructorProfile::factory()->create(['user_id' => $instructor->id]);
        Sanctum::actingAs($instructor);

        $response = $this->patchJson("/api/instructor-profiles/{$profile->id}", [
            'user_id' => $instructor->id,
            'bio' => 'Updated bio',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.bio', 'Updated bio');
    }

    public function test_update_another_instructors_profile_is_forbidden(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        $profile = InstructorProfile::factory()->create();
        Sanctum::actingAs($instructor);

        $response = $this->patchJson("/api/instructor-profiles/{$profile->id}", [
            'user_id' => $profile->user_id,
            'bio' => 'Updated bio',
        ]);

        $response->assertStatus(403);
    }

    public function test_update_as_instructor_cannot_set_approval_status(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        $profile = InstructorProfile::factory()->pending()->create(['user_id' => $instructor->id]);
        Sanctum::actingAs($instructor);

        $response = $this->patchJson("/api/instructor-profiles/{$profile->id}", [
            'user_id' => $instructor->id,
            'bio' => 'Updated bio',
            'approval_status' => 'approved',
        ]);

        $response->assertStatus(200);
        $this->assertSame('pending', $profile->fresh()->approval_status);
    }

    public function test_set_approval_status_requires_authentication(): void
    {
        $profile = InstructorProfile::factory()->pending()->create();

        $response = $this->patchJson("/api/instructor-profiles/{$profile->id}/approval-status", [
            'approval_status' => 'approved',
        ]);

        $response->assertStatus(401);
    }

    public function test_set_approval_status_as_admin_approves_instructor(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        $profile = InstructorProfile::factory()->pending()->create();
        Sanctum::actingAs($admin);

        $response = $this->patchJson("/api/instructor-profiles/{$profile->id}/approval-status", [
            'approval_status' => 'approved',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.approval_status', 'approved');
        $profile->refresh();
        $this->assertSame('approved', $profile->approval_status);
        $this->assertNotNull($profile->approved_at);
        $this->assertSame((string) $admin->id, (string) $profile->approved_by);
    }

    public function test_set_approval_status_as_admin_bans_instructor(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        $profile = InstructorProfile::factory()->create();
        Sanctum::actingAs($admin);

        $response = $this->patchJson("/api/instructor-profiles/{$profile->id}/approval-status", [
            'approval_status' => 'banned',
        ]);

        $response->assertStatus(200);
        $profile->refresh();
        $this->assertSame('banned', $profile->approval_status);
        $this->assertNull($profile->approved_at);
        $this->assertNull($profile->approved_by);
    }

    public function test_set_approval_status_as_instructor_is_forbidden(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        $profile = InstructorProfile::factory()->pending()->create(['user_id' => $instructor->id]);
        Sanctum::actingAs($instructor);

        $response = $this->patchJson("/api/instructor-profiles/{$profile->id}/approval-status", [
            'approval_status' => 'approved',
        ]);

        $response->assertStatus(403);
    }

    public function test_set_approval_status_rejects_invalid_value(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        $profile = InstructorProfile::factory()->pending()->create();
        Sanctum::actingAs($admin);

        $response = $this->patchJson("/api/instructor-profiles/{$profile->id}/approval-status", [
            'approval_status' => 'not-a-real-status',
        ]);

        $response->assertStatus(422);
    }

    public function test_set_approval_status_returns_404_for_missing_profile(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        Sanctum::actingAs($admin);

        $response = $this->patchJson('/api/instructor-profiles/' . fake()->uuid() . '/approval-status', [
            'approval_status' => 'approved',
        ]);

        $response->assertStatus(404);
    }

    public function test_delete_requires_authentication(): void
    {
        $profile = InstructorProfile::factory()->create();

        $response = $this->deleteJson("/api/instructor-profiles/{$profile->id}");

        $response->assertStatus(401);
    }

    public function test_delete_as_admin_succeeds(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        $profile = InstructorProfile::factory()->create();
        Sanctum::actingAs($admin);

        $response = $this->deleteJson("/api/instructor-profiles/{$profile->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('instructor_profiles', ['id' => $profile->id]);
    }

    public function test_delete_as_instructor_is_forbidden(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        $profile = InstructorProfile::factory()->create();
        Sanctum::actingAs($instructor);

        $response = $this->deleteJson("/api/instructor-profiles/{$profile->id}");

        $response->assertStatus(403);
    }
}

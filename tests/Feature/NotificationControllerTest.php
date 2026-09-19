<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\ScholarUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_requires_authentication(): void
    {
        $response = $this->getJson('/api/notifications');

        $response->assertStatus(401);
    }

    public function test_index_as_learner_is_scoped_to_own_notifications(): void
    {
        $user = User::factory()->create();
        $ownNotification = Notification::factory()->create(['user_id' => $user->id]);
        Notification::factory()->create(); // someone else's
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/notifications');

        $response->assertStatus(200);
        $ids = collect($response->json('data.data'))->pluck('id');
        $this->assertTrue($ids->contains((string) $ownNotification->id));
        $this->assertCount(1, $ids);
    }

    public function test_store_requires_authentication(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson('/api/notifications', [
            'user_id' => $user->id,
            'type' => 'system',
            'message' => 'Hello',
        ]);

        $response->assertStatus(401);
    }

    public function test_store_forbids_non_elevated_caller(): void
    {
        // Fixed: store() is now instructor/admin-only and validated, so an arbitrary
        // authenticated user cannot spam notifications to any user_id.
        $attacker = User::factory()->create();
        $victim = User::factory()->create();
        Sanctum::actingAs($attacker);

        $response = $this->postJson('/api/notifications', [
            'user_id' => $victim->id,
            'type' => 'system',
            'message' => 'Click this link to claim your prize',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('notifications', ['user_id' => $victim->id]);
    }


    public function test_store_accepts_quiz_review_type(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        $recipient = User::factory()->create();
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/notifications', [
            'user_id' => $recipient->id,
            'type' => 'quiz_review',
            'message' => 'A quiz needs review.',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('notifications', ['user_id' => $recipient->id, 'type' => 'quiz_review']);
    }

    public function test_store_accepts_mentor_application_type(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        $recipient = User::factory()->create();
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/notifications', [
            'user_id' => $recipient->id,
            'type' => 'mentor_application',
            'message' => 'A mentor application needs review.',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('notifications', ['user_id' => $recipient->id, 'type' => 'mentor_application']);
    }

    public function test_store_accepts_instructor_approval_type(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        $recipient = User::factory()->create();
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/notifications', [
            'user_id' => $recipient->id,
            'type' => 'instructor_approval',
            'message' => 'Your instructor account status changed.',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('notifications', ['user_id' => $recipient->id, 'type' => 'instructor_approval']);
    }

    public function test_store_accepts_course_review_type(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        $recipient = User::factory()->create();
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/notifications', [
            'user_id' => $recipient->id,
            'type' => 'course_review',
            'message' => 'Your course status changed.',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('notifications', ['user_id' => $recipient->id, 'type' => 'course_review']);
    }

    public function test_show_requires_authentication(): void
    {
        $notification = Notification::factory()->create();

        $response = $this->getJson("/api/notifications/{$notification->id}");

        $response->assertStatus(401);
    }

    public function test_show_returns_404_for_missing_notification(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/notifications/' . fake()->uuid());

        $response->assertStatus(404);
    }

    public function test_show_forbids_viewing_another_users_notification(): void
    {
        // Fixed: show() now checks ownership.
        $attacker = User::factory()->create();
        $notification = Notification::factory()->create();
        Sanctum::actingAs($attacker);

        $response = $this->getJson("/api/notifications/{$notification->id}");

        $response->assertStatus(403);
    }

    public function test_show_lets_owner_view_own_notification(): void
    {
        $user = User::factory()->create();
        $notification = Notification::factory()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/notifications/{$notification->id}");

        $response->assertStatus(200);
    }

    public function test_update_forbids_modifying_another_users_notification(): void
    {
        // Fixed: update() now checks ownership.
        $attacker = User::factory()->create();
        $notification = Notification::factory()->create(['is_read' => false]);
        Sanctum::actingAs($attacker);

        $response = $this->patchJson("/api/notifications/{$notification->id}", [
            'is_read' => true,
        ]);

        $response->assertStatus(403);
    }

    public function test_update_lets_owner_mark_own_notification_read(): void
    {
        $user = User::factory()->create();
        $notification = Notification::factory()->create(['user_id' => $user->id, 'is_read' => false]);
        Sanctum::actingAs($user);

        $response = $this->patchJson("/api/notifications/{$notification->id}", [
            'is_read' => true,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.is_read', true);
    }

    public function test_update_requires_authentication(): void
    {
        $notification = Notification::factory()->create();

        $response = $this->patchJson("/api/notifications/{$notification->id}", ['is_read' => true]);

        $response->assertStatus(401);
    }

    public function test_delete_forbids_deleting_another_users_notification(): void
    {
        // Fixed: delete() now checks ownership.
        $attacker = User::factory()->create();
        $notification = Notification::factory()->create();
        Sanctum::actingAs($attacker);

        $response = $this->deleteJson("/api/notifications/{$notification->id}");

        $response->assertStatus(403);
    }

    public function test_delete_lets_owner_delete_own_notification(): void
    {
        $user = User::factory()->create();
        $notification = Notification::factory()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/notifications/{$notification->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('notifications', ['id' => $notification->id]);
    }

    public function test_delete_requires_authentication(): void
    {
        $notification = Notification::factory()->create();

        $response = $this->deleteJson("/api/notifications/{$notification->id}");

        $response->assertStatus(401);
    }
}

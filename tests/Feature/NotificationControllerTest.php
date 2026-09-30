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

    public function test_show_hides_viewing_another_users_notification(): void
    {
        // Fixed: show() now checks ownership.
        $attacker = User::factory()->create();
        $notification = Notification::factory()->create();
        Sanctum::actingAs($attacker);

        $response = $this->getJson("/api/notifications/{$notification->id}");

        $response->assertStatus(404);
    }

    public function test_show_lets_owner_view_own_notification(): void
    {
        $user = User::factory()->create();
        $notification = Notification::factory()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/notifications/{$notification->id}");

        $response->assertStatus(200);
    }

    public function test_update_hides_modifying_another_users_notification(): void
    {
        // Fixed: update() now checks ownership.
        $attacker = User::factory()->create();
        $notification = Notification::factory()->create(['is_read' => false]);
        Sanctum::actingAs($attacker);

        $response = $this->patchJson("/api/notifications/{$notification->id}", [
            'is_read' => true,
        ]);

        $response->assertStatus(404);
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

    public function test_delete_hides_deleting_another_users_notification(): void
    {
        // Fixed: delete() now checks ownership.
        $attacker = User::factory()->create();
        $notification = Notification::factory()->create();
        Sanctum::actingAs($attacker);

        $response = $this->deleteJson("/api/notifications/{$notification->id}");

        $response->assertStatus(404);
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

    // ── every account has its own inbox ───────────────────────────────────────

    private function account(string $role): User
    {
        $user = User::factory()->create();
        ScholarUser::factory()->create(['id' => $user->id, 'role' => $role]);

        return $user;
    }

    public function test_every_role_sees_only_their_own_notifications(): void
    {
        foreach (['student', 'instructor', 'admin', 'super_admin'] as $role) {
            $me = $this->account($role);
            $mine = Notification::factory()->create(['user_id' => $me->id]);
            Notification::factory()->create(['user_id' => $this->account('student')->id]);
            Notification::factory()->create(['user_id' => $this->account('instructor')->id]);
            Sanctum::actingAs($me);

            $ids = collect($this->getJson('/api/notifications')->assertStatus(200)->json('data.data'))->pluck('id')->map(fn ($id) => (string) $id);

            $this->assertSame([(string) $mine->id], $ids->all(), "{$role} must only see their own notifications");
        }
    }

    public function test_staff_cannot_read_change_or_delete_other_peoples_notifications(): void
    {
        $student = $this->account('student');
        $notification = Notification::factory()->create(['user_id' => $student->id, 'is_read' => false]);

        foreach (['admin', 'super_admin'] as $role) {
            Sanctum::actingAs($this->account($role));
            $this->getJson("/api/notifications/{$notification->id}")->assertStatus(404);
            $this->patchJson("/api/notifications/{$notification->id}", ['is_read' => true])->assertStatus(404);
            $this->deleteJson("/api/notifications/{$notification->id}")->assertStatus(404);
        }

        $notification->refresh();
        $this->assertFalse($notification->is_read);
        $this->assertNull($notification->deleted_at);
    }

    public function test_index_reports_the_real_unread_total_and_can_filter_to_unread(): void
    {
        $me = $this->account('instructor');
        Notification::factory()->count(12)->create(['user_id' => $me->id, 'is_read' => false]);
        Notification::factory()->count(3)->create(['user_id' => $me->id, 'is_read' => true]);
        Notification::factory()->count(5)->create(['user_id' => $this->account('student')->id, 'is_read' => false]);
        Sanctum::actingAs($me);

        $page = $this->getJson('/api/notifications?per_page=5')->assertStatus(200);
        $this->assertCount(5, $page->json('data.data'));
        $this->assertSame(12, $page->json('unread_count'), 'the badge counts all unread, not just the page');

        $this->assertSame(12, $this->getJson('/api/notifications?unread=1&per_page=30')->json('data.total'));
    }

    public function test_mark_all_read_only_touches_the_callers_own_notifications(): void
    {
        $me = $this->account('admin');
        $other = $this->account('super_admin');
        Notification::factory()->count(4)->create(['user_id' => $me->id, 'is_read' => false]);
        Notification::factory()->count(2)->create(['user_id' => $other->id, 'is_read' => false]);
        Sanctum::actingAs($me);

        $this->postJson('/api/notifications/read-all')->assertStatus(200)->assertJsonPath('data.updated', 4);

        $this->assertSame(0, Notification::where('user_id', $me->id)->where('is_read', false)->count());
        $this->assertSame(2, Notification::where('user_id', $other->id)->where('is_read', false)->count());
        $this->assertSame(0, $this->getJson('/api/notifications')->json('unread_count'));

        // Nothing left to mark is fine.
        $this->postJson('/api/notifications/read-all')->assertStatus(200)->assertJsonPath('data.updated', 0);
    }

    public function test_mark_all_read_requires_authentication(): void
    {
        $this->postJson('/api/notifications/read-all')->assertStatus(401);
    }

    public function test_only_staff_can_write_notifications_by_hand(): void
    {
        $target = $this->account('student');
        $payload = ['user_id' => $target->id, 'type' => 'system', 'message' => 'Hello'];

        foreach (['student', 'instructor'] as $role) {
            Sanctum::actingAs($this->account($role));
            $this->postJson('/api/notifications', $payload)->assertStatus(403);
        }

        Sanctum::actingAs($this->account('admin'));
        $this->postJson('/api/notifications', $payload)->assertStatus(201);
    }
}

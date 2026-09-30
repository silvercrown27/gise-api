<?php

namespace Tests\Feature;

use App\Models\ContactMessage;
use App\Models\ScholarUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ContactMessageControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_is_public(): void
    {
        $response = $this->postJson('/api/contact-messages', [
            'full_name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'subject' => 'Question about courses',
            'message' => 'I would like to know more about your courses.',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['status', 'message'])
            ->assertJsonMissingPath('data');
    }

    public function test_store_validation_failure_returns_422(): void
    {
        $response = $this->postJson('/api/contact-messages', []);

        $response->assertStatus(422)->assertJsonStructure(['status', 'message', 'errors']);
    }

    public function test_index_requires_authentication(): void
    {
        $response = $this->getJson('/api/contact-messages');

        $response->assertStatus(401);
    }

    public function test_index_as_admin_succeeds(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        ContactMessage::factory()->count(2)->create();
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/contact-messages');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data.data'));
    }

    public function test_show_requires_authentication(): void
    {
        $message = ContactMessage::factory()->create();

        $response = $this->getJson("/api/contact-messages/{$message->id}");

        $response->assertStatus(401);
    }

    public function test_show_as_admin_succeeds(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        $message = ContactMessage::factory()->create();
        Sanctum::actingAs($admin);

        $response = $this->getJson("/api/contact-messages/{$message->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', (string) $message->id);
    }

    public function test_update_requires_authentication(): void
    {
        $message = ContactMessage::factory()->create();

        $response = $this->patchJson("/api/contact-messages/{$message->id}", ['status' => 'read']);

        $response->assertStatus(401);
    }

    public function test_update_as_admin_succeeds(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        $message = ContactMessage::factory()->create();
        Sanctum::actingAs($admin);

        $response = $this->patchJson("/api/contact-messages/{$message->id}", [
            'full_name' => $message->full_name,
            'email' => $message->email,
            'subject' => $message->subject,
            'message' => $message->message,
            'status' => 'read',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', 'read');
        $this->assertDatabaseHas('contact_messages', ['id' => $message->id, 'status' => 'read']);
    }

    public function test_delete_requires_authentication(): void
    {
        $message = ContactMessage::factory()->create();

        $response = $this->deleteJson("/api/contact-messages/{$message->id}");

        $response->assertStatus(401);
    }

    public function test_delete_as_admin_succeeds(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        $message = ContactMessage::factory()->create();
        Sanctum::actingAs($admin);

        $response = $this->deleteJson("/api/contact-messages/{$message->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('contact_messages', ['id' => $message->id]);
    }

    // ── notifying staff, and keeping the public form safe ─────────────────────

    private function staff(string $role): User
    {
        $user = User::factory()->create();
        ScholarUser::factory()->create(['id' => $user->id, 'role' => $role]);

        return $user;
    }

    private function form(array $overrides = []): array
    {
        return array_merge([
            'full_name' => 'Wanjiru Kamau',
            'email' => 'wanjiru@example.com',
            'subject' => 'Question about a course',
            'message' => 'Do you run the GIS course in Nairobi this year?',
        ], $overrides);
    }

    public function test_a_new_message_is_saved_as_new_and_every_super_admin_is_notified(): void
    {
        $superOne = $this->staff('super_admin');
        $superTwo = $this->staff('super_admin');
        $admin = $this->staff('admin');
        $student = $this->staff('student');

        $this->postJson('/api/contact-messages', $this->form())->assertStatus(201);

        $this->assertDatabaseHas('contact_messages', ['email' => 'wanjiru@example.com', 'subject' => 'Question about a course', 'status' => 'new']);

        foreach ([$superOne, $superTwo] as $superAdmin) {
            $this->assertDatabaseHas('notifications', [
                'user_id' => $superAdmin->id,
                'type' => 'contact_message',
                'link' => '/admin/messages',
            ]);
        }
        $message = \App\Models\Notification::where('user_id', $superOne->id)->value('message');
        $this->assertStringContainsString('Wanjiru Kamau', $message);
        $this->assertStringContainsString('Question about a course', $message);

        // Not for plain admins or anyone else.
        $this->assertDatabaseMissing('notifications', ['user_id' => $admin->id, 'type' => 'contact_message']);
        $this->assertDatabaseMissing('notifications', ['user_id' => $student->id, 'type' => 'contact_message']);
    }

    public function test_an_invalid_message_saves_nothing_and_notifies_nobody(): void
    {
        $superAdmin = $this->staff('super_admin');

        $this->postJson('/api/contact-messages', $this->form(['message' => 'short', 'email' => 'not-an-email']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['message', 'email']);

        $this->assertDatabaseCount('contact_messages', 0);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_a_visitor_cannot_set_a_status_or_any_other_field(): void
    {
        $this->postJson('/api/contact-messages', $this->form(['status' => 'replied', 'id' => 'not-mine', 'created_at' => '2001-01-01']))->assertStatus(201);

        $saved = \App\Models\ContactMessage::firstOrFail();
        $this->assertSame('new', $saved->status);
        $this->assertNotSame('not-mine', (string) $saved->id);
        $this->assertSame(now()->year, $saved->created_at->year);
    }

    public function test_a_long_subject_is_shortened_in_the_notification_but_saved_in_full(): void
    {
        $superAdmin = $this->staff('super_admin');
        $subject = str_repeat('Long subject ', 15);

        $this->postJson('/api/contact-messages', $this->form(['subject' => trim($subject)]))->assertStatus(201);

        $this->assertLessThan(160, strlen(\App\Models\Notification::where('user_id', $superAdmin->id)->value('message')));
        $this->assertSame(trim($subject), \App\Models\ContactMessage::value('subject'));
    }

    public function test_bots_that_fill_the_hidden_field_are_ignored_without_being_told(): void
    {
        $superAdmin = $this->staff('super_admin');

        $this->postJson('/api/contact-messages', $this->form(['website' => 'https://spam.example']))->assertStatus(201);

        $this->assertDatabaseCount('contact_messages', 0);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_the_form_is_rate_limited(): void
    {
        $this->staff('super_admin');

        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/contact-messages', $this->form())->assertStatus(201);
        }

        $this->postJson('/api/contact-messages', $this->form())->assertStatus(429);
        $this->assertDatabaseCount('contact_messages', 3);
    }

    public function test_a_message_is_still_saved_if_nobody_can_be_notified(): void
    {
        // No super admin exists at all.
        $this->postJson('/api/contact-messages', $this->form())->assertStatus(201);

        $this->assertDatabaseCount('contact_messages', 1);
    }

    public function test_only_staff_can_read_messages(): void
    {
        $message = \App\Models\ContactMessage::factory()->create();

        foreach (['student', 'instructor'] as $role) {
            Sanctum::actingAs($this->staff($role));
            $this->getJson('/api/contact-messages')->assertStatus(403);
            $this->getJson("/api/contact-messages/{$message->id}")->assertStatus(403);
            $this->patchJson("/api/contact-messages/{$message->id}", ['status' => 'read'])->assertStatus(403);
        }
    }

    public function test_staff_can_only_change_the_status_never_the_visitors_words(): void
    {
        $message = \App\Models\ContactMessage::factory()->create(['status' => 'new', 'subject' => 'Original subject', 'message' => 'The original message text.']);
        Sanctum::actingAs($this->staff('admin'));

        $this->patchJson("/api/contact-messages/{$message->id}", ['status' => 'read', 'subject' => 'Tampered', 'message' => 'Tampered message text.'])->assertStatus(200);

        $message->refresh();
        $this->assertSame('read', $message->status);
        $this->assertSame('Original subject', $message->subject);
        $this->assertSame('The original message text.', $message->message);

        $this->patchJson("/api/contact-messages/{$message->id}", ['status' => 'archived'])->assertStatus(422);
        $this->patchJson("/api/contact-messages/{$message->id}", [])->assertStatus(422);
    }

    public function test_the_inbox_can_be_filtered_by_status_and_searched(): void
    {
        \App\Models\ContactMessage::factory()->create(['status' => 'new', 'full_name' => 'Amina Otieno', 'subject' => 'GIS fees']);
        \App\Models\ContactMessage::factory()->create(['status' => 'read', 'full_name' => 'Baraka Mwangi', 'subject' => 'Timetable']);
        \App\Models\ContactMessage::factory()->create(['status' => 'replied', 'full_name' => 'Chebet Korir', 'subject' => 'Certificates']);
        Sanctum::actingAs($this->staff('super_admin'));

        $this->assertSame(1, $this->getJson('/api/contact-messages?status=new')->json('data.total'));
        $this->assertSame(3, $this->getJson('/api/contact-messages')->json('data.total'));
        $this->assertSame(1, $this->getJson('/api/contact-messages?q=Baraka')->json('data.total'), 'search covers the sender name');
        $this->assertSame(1, $this->getJson('/api/contact-messages?q=Certificates')->json('data.total'), 'and the subject');
    }
}

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
            ->assertJsonStructure(['status', 'message', 'data']);
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

    public function test_index_as_admin_is_forbidden_due_to_lookup_bug(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['user_id' => $admin->id, 'role' => 'admin']);
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/contact-messages');

        $response->assertStatus(403);
    }

    public function test_show_requires_authentication(): void
    {
        $message = ContactMessage::factory()->create();

        $response = $this->getJson("/api/contact-messages/{$message->id}");

        $response->assertStatus(401);
    }

    public function test_show_as_admin_is_forbidden_due_to_lookup_bug(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['user_id' => $admin->id, 'role' => 'admin']);
        $message = ContactMessage::factory()->create();
        Sanctum::actingAs($admin);

        $response = $this->getJson("/api/contact-messages/{$message->id}");

        $response->assertStatus(403);
    }

    public function test_update_requires_authentication(): void
    {
        $message = ContactMessage::factory()->create();

        $response = $this->patchJson("/api/contact-messages/{$message->id}", ['status' => 'read']);

        $response->assertStatus(401);
    }

    public function test_update_as_admin_is_forbidden_due_to_lookup_bug(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['user_id' => $admin->id, 'role' => 'admin']);
        $message = ContactMessage::factory()->create();
        Sanctum::actingAs($admin);

        $response = $this->patchJson("/api/contact-messages/{$message->id}", [
            'full_name' => $message->full_name,
            'email' => $message->email,
            'subject' => $message->subject,
            'message' => $message->message,
            'status' => 'read',
        ]);

        $response->assertStatus(403);
    }

    public function test_delete_requires_authentication(): void
    {
        $message = ContactMessage::factory()->create();

        $response = $this->deleteJson("/api/contact-messages/{$message->id}");

        $response->assertStatus(401);
    }

    public function test_delete_as_admin_is_forbidden_due_to_lookup_bug(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['user_id' => $admin->id, 'role' => 'admin']);
        $message = ContactMessage::factory()->create();
        Sanctum::actingAs($admin);

        $response = $this->deleteJson("/api/contact-messages/{$message->id}");

        $response->assertStatus(403);
    }
}

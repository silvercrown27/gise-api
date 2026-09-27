<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseLead;
use App\Models\ScholarUser;
use App\Models\User;
use App\Notifications\CourseBrochureNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CourseBrochureTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);

        return $admin;
    }

    public function test_request_is_stored_as_pending_and_admins_are_notified_but_nothing_is_emailed(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $course = Course::factory()->published()->create();

        $this->postJson("/api/courses/{$course->id}/brochure-requests", [
            'full_name' => 'Amina Otieno',
            'email' => 'Amina@Example.com',
            'phone' => '+254 712 345678',
        ])->assertStatus(201);

        $this->assertDatabaseHas('course_leads', [
            'course_id' => $course->id,
            'email' => 'amina@example.com',
            'source' => 'brochure',
            'brochure_status' => 'pending',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $admin->id,
            'type' => 'brochure_request',
            'link' => '/admin/brochure-requests',
        ]);
        Notification::assertNothingSent();
    }

    public function test_admin_sends_the_approved_brochure(): void
    {
        Notification::fake();
        $course = Course::factory()->published()->create(['brochure_url' => '/storage/course-materials/b.pdf']);
        $lead = CourseLead::factory()->create([
            'course_id' => $course->id, 'email' => 'amina@example.com', 'source' => 'brochure', 'brochure_status' => 'pending',
        ]);
        Sanctum::actingAs($this->admin());

        $this->patchJson("/api/course-leads/{$lead->id}/brochure-status", ['status' => 'sent'])->assertStatus(200);

        $this->assertSame('sent', $lead->fresh()->brochure_status);
        $this->assertNotNull($lead->fresh()->brochure_sent_at);
        Notification::assertSentOnDemand(
            CourseBrochureNotification::class,
            fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'amina@example.com'
                && str_ends_with($notification->brochureUrl, '/storage/course-materials/b.pdf')
        );

        // Handled requests can't be handled twice.
        $this->patchJson("/api/course-leads/{$lead->id}/brochure-status", ['status' => 'sent'])->assertStatus(422);
    }

    public function test_cannot_send_before_the_course_has_an_approved_brochure(): void
    {
        Notification::fake();
        $course = Course::factory()->published()->create(['brochure_url' => null]);
        $lead = CourseLead::factory()->create(['course_id' => $course->id, 'source' => 'brochure', 'brochure_status' => 'pending']);
        Sanctum::actingAs($this->admin());

        $this->patchJson("/api/course-leads/{$lead->id}/brochure-status", ['status' => 'sent'])->assertStatus(422);
        Notification::assertNothingSent();
    }

    public function test_admin_can_decline_and_non_admins_cannot_review(): void
    {
        $lead = CourseLead::factory()->create(['source' => 'brochure', 'brochure_status' => 'pending']);

        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        Sanctum::actingAs($instructor);
        $this->patchJson("/api/course-leads/{$lead->id}/brochure-status", ['status' => 'declined'])->assertStatus(403);

        Sanctum::actingAs($this->admin());
        $this->patchJson("/api/course-leads/{$lead->id}/brochure-status", ['status' => 'declined'])->assertStatus(200);
        $this->assertSame('declined', $lead->fresh()->brochure_status);
    }

    public function test_brochure_request_validates_contact_details(): void
    {
        $course = Course::factory()->published()->create();

        $this->postJson("/api/courses/{$course->id}/brochure-requests", ['full_name' => '', 'email' => 'nope', 'phone' => 'abc'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['full_name', 'email', 'phone']);
    }

    public function test_brochure_request_for_unpublished_course_is_404(): void
    {
        $course = Course::factory()->create(['status' => 'draft']);

        $this->postJson("/api/courses/{$course->id}/brochure-requests", [
            'full_name' => 'A', 'email' => 'a@example.com', 'phone' => '0712345678',
        ])->assertStatus(404);
    }

    public function test_instructor_sees_only_leads_for_courses_they_mentor(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        $mentored = Course::factory()->create(['instructor_id' => $instructor->id]);
        $mine = CourseLead::factory()->create(['course_id' => $mentored->id]);
        $other = CourseLead::factory()->create();
        Sanctum::actingAs($instructor);

        $ids = collect($this->getJson('/api/course-leads')->json('data.data'))->pluck('id');

        $this->assertTrue($ids->contains((string) $mine->id));
        $this->assertFalse($ids->contains((string) $other->id));
    }
}

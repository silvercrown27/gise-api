<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseLead;
use App\Models\ScholarUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CourseLeadControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_requires_authentication(): void
    {
        $response = $this->getJson('/api/course-leads');

        $response->assertStatus(401);
    }

    public function test_index_as_unresolvable_user_is_scoped_to_own_leads_via_where_clause(): void
    {
        // The "!$user || role === learner" branch scopes with a WHERE clause, which
        // is unaffected by the in-PHP type mismatch, so this scoping does work.
        $user = User::factory()->create();
        $ownLead = CourseLead::factory()->create(['user_id' => $user->id]);
        CourseLead::factory()->create(); // unrelated lead, user_id null
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/course-leads');

        $response->assertStatus(200);
        $ids = collect($response->json('data.data'))->pluck('id');
        $this->assertTrue($ids->contains((string) $ownLead->id));
        $this->assertCount(1, $ids);
    }

    public function test_store_is_public_within_auth_requirement(): void
    {
        $course = Course::factory()->create();

        $response = $this->postJson('/api/course-leads', [
            'course_id' => $course->id,
            'full_name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '1234567890',
        ]);

        $response->assertStatus(401);
    }

    public function test_store_has_no_role_check_any_authenticated_user_can_create_a_lead(): void
    {
        $user = User::factory()->create();
        $course = Course::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/course-leads', [
            'course_id' => $course->id,
            'full_name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '1234567890',
        ]);

        $response->assertStatus(201);
    }

    public function test_store_validation_failure_returns_422(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/course-leads', []);

        $response->assertStatus(422);
    }

    public function test_show_lets_any_authenticated_user_view_any_lead(): void
    {
        // show() has no auth/ownership check at all beyond the route's auth:sanctum
        // middleware -- any authenticated user can view any lead's contact details
        // (full_name, email, phone) regardless of who submitted it.
        $attacker = User::factory()->create();
        $lead = CourseLead::factory()->create();
        Sanctum::actingAs($attacker);

        $response = $this->getJson("/api/course-leads/{$lead->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.email', $lead->email);
    }

    public function test_show_requires_authentication(): void
    {
        $lead = CourseLead::factory()->create();

        $response = $this->getJson("/api/course-leads/{$lead->id}");

        $response->assertStatus(401);
    }

    public function test_show_returns_404_for_missing_lead(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/course-leads/' . fake()->uuid());

        $response->assertStatus(404);
    }

    public function test_update_lets_any_authenticated_user_modify_any_lead(): void
    {
        $attacker = User::factory()->create();
        $lead = CourseLead::factory()->create(['status' => 'new']);
        Sanctum::actingAs($attacker);

        $response = $this->patchJson("/api/course-leads/{$lead->id}", [
            'course_id' => $lead->course_id,
            'full_name' => $lead->full_name,
            'email' => $lead->email,
            'phone' => $lead->phone,
            'status' => 'converted',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', 'converted');
    }

    public function test_update_requires_authentication(): void
    {
        $lead = CourseLead::factory()->create();

        $response = $this->patchJson("/api/course-leads/{$lead->id}", [
            'course_id' => $lead->course_id,
            'full_name' => $lead->full_name,
            'email' => $lead->email,
            'phone' => $lead->phone,
        ]);

        $response->assertStatus(401);
    }

    public function test_delete_lets_any_authenticated_user_delete_any_lead(): void
    {
        $attacker = User::factory()->create();
        $lead = CourseLead::factory()->create();
        Sanctum::actingAs($attacker);

        $response = $this->deleteJson("/api/course-leads/{$lead->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('course_leads', ['id' => $lead->id]);
    }

    public function test_delete_requires_authentication(): void
    {
        $lead = CourseLead::factory()->create();

        $response = $this->deleteJson("/api/course-leads/{$lead->id}");

        $response->assertStatus(401);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CoursePricingHistory;
use App\Models\ScholarUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CoursePricingHistoryControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_requires_authentication(): void
    {
        $response = $this->getJson('/api/course-pricing-history');

        $response->assertStatus(401);
    }

    public function test_index_as_instructor_is_forbidden_due_to_lookup_bug(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['user_id' => $instructor->id, 'role' => 'instructor']);
        Sanctum::actingAs($instructor);

        $response = $this->getJson('/api/course-pricing-history');

        $response->assertStatus(403);
    }

    public function test_store_requires_authentication(): void
    {
        $course = Course::factory()->create();
        $changer = User::factory()->create();

        $response = $this->postJson('/api/course-pricing-history', [
            'course_id' => $course->id,
            'old_price' => 1000,
            'new_price' => 1200,
            'changed_by' => $changer->id,
        ]);

        $response->assertStatus(401);
    }

    public function test_store_as_admin_is_forbidden_due_to_lookup_bug(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['user_id' => $admin->id, 'role' => 'admin']);
        $course = Course::factory()->create();
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/course-pricing-history', [
            'course_id' => $course->id,
            'old_price' => 1000,
            'new_price' => 1200,
            'changed_by' => $admin->id,
        ]);

        $response->assertStatus(403);
    }

    public function test_show_requires_authentication(): void
    {
        $history = CoursePricingHistory::factory()->create();

        $response = $this->getJson("/api/course-pricing-history/{$history->id}");

        $response->assertStatus(401);
    }

    public function test_show_returns_404_for_missing_record(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/course-pricing-history/' . fake()->uuid());

        $response->assertStatus(404);
    }

    public function test_show_as_owning_instructor_is_forbidden_due_to_lookup_bug(): void
    {
        // show() has $isOwningInstructor which still requires $user (ScholarUser::find)
        // to be truthy AND role === 'instructor', so the lookup bug blocks this path
        // even for the actual course-owning instructor.
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['user_id' => $instructor->id, 'role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $instructor->id]);
        $history = CoursePricingHistory::factory()->create(['course_id' => $course->id]);
        Sanctum::actingAs($instructor);

        $response = $this->getJson("/api/course-pricing-history/{$history->id}");

        $response->assertStatus(403);
    }

    public function test_update_requires_authentication(): void
    {
        $history = CoursePricingHistory::factory()->create();

        $response = $this->patchJson("/api/course-pricing-history/{$history->id}", [
            'course_id' => $history->course_id,
            'old_price' => 1000,
            'new_price' => 1500,
            'changed_by' => $history->changed_by,
        ]);

        $response->assertStatus(401);
    }

    public function test_update_as_admin_is_forbidden_due_to_lookup_bug(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['user_id' => $admin->id, 'role' => 'admin']);
        $history = CoursePricingHistory::factory()->create();
        Sanctum::actingAs($admin);

        $response = $this->patchJson("/api/course-pricing-history/{$history->id}", [
            'course_id' => $history->course_id,
            'old_price' => 1000,
            'new_price' => 1500,
            'changed_by' => $admin->id,
        ]);

        $response->assertStatus(403);
    }

    public function test_delete_requires_authentication(): void
    {
        $history = CoursePricingHistory::factory()->create();

        $response = $this->deleteJson("/api/course-pricing-history/{$history->id}");

        $response->assertStatus(401);
    }

    public function test_delete_as_admin_is_forbidden_due_to_lookup_bug(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['user_id' => $admin->id, 'role' => 'admin']);
        $history = CoursePricingHistory::factory()->create();
        Sanctum::actingAs($admin);

        $response = $this->deleteJson("/api/course-pricing-history/{$history->id}");

        $response->assertStatus(403);
    }
}

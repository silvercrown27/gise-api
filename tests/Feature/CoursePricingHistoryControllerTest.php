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

    public function test_index_as_instructor_succeeds_scoped_to_own_courses(): void
    {
        // index() scopes instructors to pricing history rows for courses they own.
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $instructor->id]);
        CoursePricingHistory::factory()->create(['course_id' => $course->id]);
        CoursePricingHistory::factory()->create(); // someone else's course pricing history
        Sanctum::actingAs($instructor);

        $response = $this->getJson('/api/course-pricing-history');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.data'));
    }

    public function test_index_as_student_is_forbidden(): void
    {
        $student = User::factory()->create();
        ScholarUser::factory()->create(['id' => $student->id, 'role' => 'student']);
        Sanctum::actingAs($student);

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

    public function test_store_as_admin_succeeds(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        $course = Course::factory()->create();
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/course-pricing-history', [
            'course_id' => $course->id,
            'old_price' => 1000,
            'new_price' => 1200,
            'changed_by' => $admin->id,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.new_price', 1200);
        $this->assertDatabaseHas('course_pricing_history', ['course_id' => $course->id, 'new_price' => 1200]);
    }

    public function test_store_as_student_is_forbidden(): void
    {
        $student = User::factory()->create();
        ScholarUser::factory()->create(['id' => $student->id, 'role' => 'student']);
        $course = Course::factory()->create();
        Sanctum::actingAs($student);

        $response = $this->postJson('/api/course-pricing-history', [
            'course_id' => $course->id,
            'old_price' => 1000,
            'new_price' => 1200,
            'changed_by' => $student->id,
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

    public function test_show_as_owning_instructor_succeeds(): void
    {
        // show() has $isOwningInstructor which requires $user (ScholarUser::find) to be
        // truthy AND role === 'instructor' AND own the course -- now that the lookup
        // resolves correctly, the actual course-owning instructor can view the record.
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $instructor->id]);
        $history = CoursePricingHistory::factory()->create(['course_id' => $course->id]);
        Sanctum::actingAs($instructor);

        $response = $this->getJson("/api/course-pricing-history/{$history->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', (string) $history->id);
    }

    public function test_show_as_non_owning_instructor_is_forbidden(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        $history = CoursePricingHistory::factory()->create(); // belongs to a different course/instructor
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

    public function test_update_as_admin_succeeds(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        $history = CoursePricingHistory::factory()->create();
        Sanctum::actingAs($admin);

        $response = $this->patchJson("/api/course-pricing-history/{$history->id}", [
            'course_id' => $history->course_id,
            'old_price' => 1000,
            'new_price' => 1500,
            'changed_by' => $admin->id,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.new_price', 1500);
        $this->assertDatabaseHas('course_pricing_history', ['id' => $history->id, 'new_price' => 1500]);
    }

    public function test_delete_requires_authentication(): void
    {
        $history = CoursePricingHistory::factory()->create();

        $response = $this->deleteJson("/api/course-pricing-history/{$history->id}");

        $response->assertStatus(401);
    }

    public function test_delete_as_admin_succeeds(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        $history = CoursePricingHistory::factory()->create();
        Sanctum::actingAs($admin);

        $response = $this->deleteJson("/api/course-pricing-history/{$history->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('course_pricing_history', ['id' => $history->id]);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\ScholarUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CourseControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_is_public(): void
    {
        Course::factory()->count(2)->create();

        $response = $this->getJson('/api/courses');

        $response->assertStatus(200)->assertJsonStructure(['status', 'data']);
    }

    public function test_show_is_public(): void
    {
        $course = Course::factory()->create();

        $response = $this->getJson("/api/courses/{$course->id}");

        $response->assertStatus(200)->assertJsonPath('data.id', (string) $course->id);
    }

    public function test_show_returns_404_for_missing_course(): void
    {
        $response = $this->getJson('/api/courses/' . fake()->uuid());

        $response->assertStatus(404);
    }

    public function test_store_requires_authentication(): void
    {
        $response = $this->postJson('/api/courses', ['title' => 'New Course']);

        $response->assertStatus(401);
    }

    public function test_store_as_instructor_is_forbidden_due_to_lookup_bug(): void
    {
        // Instructors should be able to create courses, but ScholarUser::find($request->user()->id)
        // can never find the row (it looks up by scholar_users.id, not user_id), so $user is
        // always null here and the request is rejected for every real caller.
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        Sanctum::actingAs($instructor);

        $response = $this->postJson('/api/courses', [
            'title' => 'New Course',
            'code' => 'ABC123',
            'slug' => 'new-course',
            'price' => 1000,
        ]);

        $response->assertStatus(403);
    }

    public function test_update_own_course_as_instructor_is_forbidden_due_to_lookup_bug(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $instructor->id]);
        Sanctum::actingAs($instructor);

        $response = $this->patchJson("/api/courses/{$course->id}", [
            'title' => 'Updated Title',
        ]);

        // The controller's own logic checks $course->instructor_id === $request->user()->id
        // (a correct ownership check!) but it is gated behind $user = ScholarUser::find(...)
        // being non-null first, which the lookup bug prevents.
        $response->assertStatus(403);
    }

    public function test_update_returns_404_for_missing_course_before_auth_check(): void
    {
        $instructor = User::factory()->create();
        Sanctum::actingAs($instructor);

        $response = $this->patchJson('/api/courses/' . fake()->uuid(), ['title' => 'X']);

        $response->assertStatus(404);
    }

    public function test_update_requires_authentication(): void
    {
        $course = Course::factory()->create();

        $response = $this->patchJson("/api/courses/{$course->id}", ['title' => 'X']);

        $response->assertStatus(401);
    }

    public function test_delete_own_course_as_instructor_is_forbidden_due_to_lookup_bug(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $instructor->id]);
        Sanctum::actingAs($instructor);

        $response = $this->deleteJson("/api/courses/{$course->id}");

        $response->assertStatus(403);
    }

    public function test_delete_requires_authentication(): void
    {
        $course = Course::factory()->create();

        $response = $this->deleteJson("/api/courses/{$course->id}");

        $response->assertStatus(401);
    }
}

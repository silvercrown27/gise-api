<?php

namespace Tests\Feature;

use App\Models\CourseLesson;
use App\Models\CourseModule;
use App\Models\ScholarUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CourseLessonControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_is_public(): void
    {
        CourseLesson::factory()->count(2)->create();

        $response = $this->getJson('/api/course-lessons');

        $response->assertStatus(200)->assertJsonStructure(['status', 'data']);
    }

    public function test_show_is_public(): void
    {
        $lesson = CourseLesson::factory()->create();

        $response = $this->getJson("/api/course-lessons/{$lesson->id}");

        $response->assertStatus(200)->assertJsonPath('data.id', (string) $lesson->id);
    }

    public function test_show_returns_404_for_missing_lesson(): void
    {
        $response = $this->getJson('/api/course-lessons/' . fake()->uuid());

        $response->assertStatus(404);
    }

    public function test_store_requires_authentication(): void
    {
        $module = CourseModule::factory()->create();

        $response = $this->postJson('/api/course-lessons', [
            'module_id' => $module->id,
            'title' => 'Lesson 1',
            'content_type' => 'video',
        ]);

        $response->assertStatus(401);
    }

    public function test_store_as_instructor_is_forbidden_due_to_lookup_bug(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['user_id' => $instructor->id, 'role' => 'instructor']);
        $module = CourseModule::factory()->create();
        Sanctum::actingAs($instructor);

        $response = $this->postJson('/api/course-lessons', [
            'module_id' => $module->id,
            'title' => 'Lesson 1',
            'content_type' => 'video',
        ]);

        $response->assertStatus(403);
    }

    public function test_store_validation_failure_is_masked_by_403(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['user_id' => $admin->id, 'role' => 'admin']);
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/course-lessons', []);

        $response->assertStatus(403);
    }

    public function test_update_requires_authentication(): void
    {
        $lesson = CourseLesson::factory()->create();

        $response = $this->patchJson("/api/course-lessons/{$lesson->id}", ['title' => 'X']);

        $response->assertStatus(401);
    }

    public function test_update_as_admin_is_forbidden_due_to_lookup_bug(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['user_id' => $admin->id, 'role' => 'admin']);
        $lesson = CourseLesson::factory()->create();
        Sanctum::actingAs($admin);

        $response = $this->patchJson("/api/course-lessons/{$lesson->id}", ['title' => 'Updated']);

        $response->assertStatus(403);
    }

    public function test_delete_requires_authentication(): void
    {
        $lesson = CourseLesson::factory()->create();

        $response = $this->deleteJson("/api/course-lessons/{$lesson->id}");

        $response->assertStatus(401);
    }

    public function test_delete_as_admin_is_forbidden_due_to_lookup_bug(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['user_id' => $admin->id, 'role' => 'admin']);
        $lesson = CourseLesson::factory()->create();
        Sanctum::actingAs($admin);

        $response = $this->deleteJson("/api/course-lessons/{$lesson->id}");

        $response->assertStatus(403);
    }
}

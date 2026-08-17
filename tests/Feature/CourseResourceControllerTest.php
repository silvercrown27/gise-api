<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseResource;
use App\Models\ScholarUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CourseResourceControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_is_public(): void
    {
        CourseResource::factory()->count(2)->create();

        $response = $this->getJson('/api/course-resources');

        $response->assertStatus(200)->assertJsonStructure(['status', 'data']);
    }

    public function test_show_is_public(): void
    {
        $resource = CourseResource::factory()->create();

        $response = $this->getJson("/api/course-resources/{$resource->id}");

        $response->assertStatus(200)->assertJsonPath('data.id', (string) $resource->id);
    }

    public function test_show_returns_404_for_missing_resource(): void
    {
        $response = $this->getJson('/api/course-resources/' . fake()->uuid());

        $response->assertStatus(404);
    }

    public function test_store_requires_authentication(): void
    {
        $course = Course::factory()->create();

        $response = $this->postJson('/api/course-resources', [
            'course_id' => $course->id,
            'title' => 'Slides',
            'file_url' => 'https://example.com/slides.pdf',
        ]);

        $response->assertStatus(401);
    }

    public function test_store_as_instructor_succeeds(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $instructor->id]);
        Sanctum::actingAs($instructor);

        $response = $this->postJson('/api/course-resources', [
            'course_id' => $course->id,
            'title' => 'Slides',
            'file_url' => 'https://example.com/slides.pdf',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.title', 'Slides');
        $this->assertDatabaseHas('course_resources', ['course_id' => $course->id, 'title' => 'Slides']);
    }

    public function test_store_as_student_is_forbidden(): void
    {
        $student = User::factory()->create();
        ScholarUser::factory()->create(['id' => $student->id, 'role' => 'student']);
        $course = Course::factory()->create();
        Sanctum::actingAs($student);

        $response = $this->postJson('/api/course-resources', [
            'course_id' => $course->id,
            'title' => 'Slides',
            'file_url' => 'https://example.com/slides.pdf',
        ]);

        $response->assertStatus(403);
    }

    public function test_update_requires_authentication(): void
    {
        $resource = CourseResource::factory()->create();

        $response = $this->patchJson("/api/course-resources/{$resource->id}", ['title' => 'X']);

        $response->assertStatus(401);
    }

    public function test_update_as_admin_succeeds(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        $resource = CourseResource::factory()->create();
        Sanctum::actingAs($admin);

        $response = $this->patchJson("/api/course-resources/{$resource->id}", [
            'course_id' => $resource->course_id,
            'title' => 'Updated',
            'file_url' => $resource->file_url,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.title', 'Updated');
        $this->assertDatabaseHas('course_resources', ['id' => $resource->id, 'title' => 'Updated']);
    }

    public function test_delete_requires_authentication(): void
    {
        $resource = CourseResource::factory()->create();

        $response = $this->deleteJson("/api/course-resources/{$resource->id}");

        $response->assertStatus(401);
    }

    public function test_delete_as_admin_succeeds(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        $resource = CourseResource::factory()->create();
        Sanctum::actingAs($admin);

        $response = $this->deleteJson("/api/course-resources/{$resource->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('course_resources', ['id' => $resource->id]);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\CourseModule;
use App\Models\ScholarUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

    public function test_store_as_instructor_succeeds(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $instructor->id]);
        $module = CourseModule::factory()->create(['course_id' => $course->id]);
        Sanctum::actingAs($instructor);

        $response = $this->postJson('/api/course-lessons', [
            'module_id' => $module->id,
            'title' => 'Lesson 1',
            'content_type' => 'video',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.title', 'Lesson 1');
        $this->assertDatabaseHas('course_lessons', ['module_id' => $module->id, 'title' => 'Lesson 1']);
    }

    public function test_store_with_uploaded_pdf_sets_content_url(): void
    {
        Storage::fake('public');

        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $instructor->id]);
        $module = CourseModule::factory()->create(['course_id' => $course->id]);
        Sanctum::actingAs($instructor);

        $response = $this->post('/api/course-lessons', [
            'module_id' => (string) $module->id,
            'title' => 'Lecture notes',
            'content_type' => 'pdf',
            'content_file' => UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json']);

        $response->assertStatus(201);
        $contentUrl = $response->json('data.content_url_or_body');
        $this->assertNotEmpty($contentUrl);
        $this->assertStringContainsString('course-lessons', $contentUrl);
    }

    public function test_store_as_student_is_forbidden(): void
    {
        $student = User::factory()->create();
        ScholarUser::factory()->create(['id' => $student->id, 'role' => 'student']);
        $module = CourseModule::factory()->create();
        Sanctum::actingAs($student);

        $response = $this->postJson('/api/course-lessons', [
            'module_id' => $module->id,
            'title' => 'Lesson 1',
            'content_type' => 'video',
        ]);

        $response->assertStatus(403);
    }

    public function test_store_validation_failure_returns_422(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/course-lessons', []);

        $response->assertStatus(422);
    }

    public function test_update_requires_authentication(): void
    {
        $lesson = CourseLesson::factory()->create();

        $response = $this->patchJson("/api/course-lessons/{$lesson->id}", ['title' => 'X']);

        $response->assertStatus(401);
    }

    public function test_update_as_admin_succeeds(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        $lesson = CourseLesson::factory()->create();
        Sanctum::actingAs($admin);

        $response = $this->patchJson("/api/course-lessons/{$lesson->id}", [
            'module_id' => $lesson->module_id,
            'title' => 'Updated',
            'content_type' => $lesson->content_type,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.title', 'Updated');
        $this->assertDatabaseHas('course_lessons', ['id' => $lesson->id, 'title' => 'Updated']);
    }

    public function test_update_with_uploaded_pdf_replaces_content_url(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        $lesson = CourseLesson::factory()->create(['content_type' => 'pdf']);
        Sanctum::actingAs($admin);

        $response = $this->post("/api/course-lessons/{$lesson->id}", [
            '_method' => 'PATCH',
            'module_id' => (string) $lesson->module_id,
            'title' => $lesson->title,
            'content_type' => 'pdf',
            'content_file' => UploadedFile::fake()->create('updated.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json']);

        $response->assertStatus(200);
        $contentUrl = $response->json('data.content_url_or_body');
        $this->assertNotEmpty($contentUrl);
        $this->assertStringContainsString('course-lessons', $contentUrl);
    }

    public function test_delete_requires_authentication(): void
    {
        $lesson = CourseLesson::factory()->create();

        $response = $this->deleteJson("/api/course-lessons/{$lesson->id}");

        $response->assertStatus(401);
    }

    public function test_delete_as_admin_succeeds(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        $lesson = CourseLesson::factory()->create();
        Sanctum::actingAs($admin);

        $response = $this->deleteJson("/api/course-lessons/{$lesson->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('course_lessons', ['id' => $lesson->id]);
    }
}

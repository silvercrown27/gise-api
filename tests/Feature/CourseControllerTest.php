<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use App\Models\ScholarUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

    public function test_mine_requires_authentication(): void
    {
        $response = $this->getJson('/api/courses/mine');

        $response->assertStatus(401);
    }

    public function test_mine_returns_only_own_courses_including_drafts(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        Course::factory()->create(['instructor_id' => $instructor->id, 'status' => 'draft']);
        Course::factory()->create(['instructor_id' => $instructor->id, 'status' => 'published']);
        Course::factory()->create(); // someone else's course
        Sanctum::actingAs($instructor);

        $response = $this->getJson('/api/courses/mine');

        $response->assertStatus(200);
        $ids = collect($response->json('data.data'))->pluck('instructor_id');
        $this->assertCount(2, $ids);
        $this->assertTrue($ids->every(fn ($id) => $id === (string) $instructor->id));
    }

    public function test_summary_requires_authentication(): void
    {
        $response = $this->getJson('/api/courses/summary');

        $response->assertStatus(401);
    }

    public function test_summary_aggregates_own_courses_registrations_and_earnings(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);

        $publishedCourse = Course::factory()->create([
            'instructor_id' => $instructor->id,
            'status' => 'published',
        ]);
        Course::factory()->create(['instructor_id' => $instructor->id, 'status' => 'draft']);

        \App\Models\Enrollment::factory()->count(3)->create(['course_id' => $publishedCourse->id]);

        \App\Models\InstructorPayout::factory()->create([
            'instructor_id' => $instructor->id,
            'status' => 'paid',
            'net_amount' => 5000,
        ]);
        \App\Models\InstructorPayout::factory()->create([
            'instructor_id' => $instructor->id,
            'status' => 'paid',
            'net_amount' => 3000,
        ]);
        \App\Models\InstructorPayout::factory()->create([
            'instructor_id' => $instructor->id,
            'status' => 'pending',
            'net_amount' => 1500,
        ]);

        Sanctum::actingAs($instructor);

        $response = $this->getJson('/api/courses/summary');

        $response->assertStatus(200);
        $response->assertJsonPath('data.total_courses', 2);
        $response->assertJsonPath('data.published_courses', 1);
        $response->assertJsonPath('data.total_registrations', 3);
        $response->assertJsonPath('data.total_earnings', 8000);
        $response->assertJsonPath('data.pending_earnings', 1500);
    }

    public function test_curriculum_requires_authentication(): void
    {
        $course = Course::factory()->create();

        $response = $this->getJson("/api/courses/{$course->id}/curriculum");

        $response->assertStatus(401);
    }

    public function test_curriculum_returns_404_for_missing_course(): void
    {
        $student = User::factory()->create();
        ScholarUser::factory()->create(['id' => $student->id, 'role' => 'student']);
        Sanctum::actingAs($student);

        $response = $this->getJson('/api/courses/' . fake()->uuid() . '/curriculum');

        $response->assertStatus(404);
    }

    public function test_curriculum_forbids_non_enrolled_student(): void
    {
        $student = User::factory()->create();
        ScholarUser::factory()->create(['id' => $student->id, 'role' => 'student']);
        $course = Course::factory()->create();
        Sanctum::actingAs($student);

        $response = $this->getJson("/api/courses/{$course->id}/curriculum");

        $response->assertStatus(403);
    }

    public function test_curriculum_returns_modules_lessons_and_resources_for_enrolled_student(): void
    {
        $student = User::factory()->create();
        ScholarUser::factory()->create(['id' => $student->id, 'role' => 'student']);
        $course = Course::factory()->create();
        $enrollment = \App\Models\Enrollment::factory()->create([
            'learner_id' => $student->id,
            'course_id' => $course->id,
        ]);

        $module = \App\Models\CourseModule::factory()->create(['course_id' => $course->id, 'order_index' => 0]);
        $lesson = \App\Models\CourseLesson::factory()->create(['module_id' => $module->id, 'order_index' => 0]);
        \App\Models\CourseResource::factory()->create(['course_id' => $course->id, 'lesson_id' => $lesson->id]);

        \App\Models\LessonProgress::factory()->create([
            'enrollment_id' => $enrollment->id,
            'lesson_id' => $lesson->id,
            'status' => 'completed',
        ]);

        Sanctum::actingAs($student);

        $response = $this->getJson("/api/courses/{$course->id}/curriculum");

        $response->assertStatus(200);
        $response->assertJsonPath('data.course.id', (string) $course->id);
        $response->assertJsonPath('data.modules.0.id', (string) $module->id);
        $response->assertJsonPath('data.modules.0.lessons.0.id', (string) $lesson->id);
        $response->assertJsonPath('data.modules.0.lessons.0.progress_status', 'completed');
        $response->assertJsonCount(1, 'data.modules.0.lessons.0.resources');
    }

    public function test_curriculum_is_accessible_to_owning_instructor_without_enrollment(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $instructor->id]);
        Sanctum::actingAs($instructor);

        $response = $this->getJson("/api/courses/{$course->id}/curriculum");

        $response->assertStatus(200);
    }

    public function test_show_includes_category_and_instructor(): void
    {
        $instructor = User::factory()->create(['name' => 'Jane Doe']);
        $category = Category::factory()->create(['name' => 'Software Engineering']);
        $course = Course::factory()->create([
            'instructor_id' => $instructor->id,
            'category_id' => $category->id,
        ]);

        $response = $this->getJson("/api/courses/{$course->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.instructor.name', 'Jane Doe');
        $response->assertJsonPath('data.category.name', 'Software Engineering');
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

    public function test_store_as_instructor_succeeds(): void
    {
        // Instructors can create courses. store() forces instructor_id to the caller's
        // own id for non-admins, so the created course is attributed to them.
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        Sanctum::actingAs($instructor);

        $response = $this->postJson('/api/courses', [
            'title' => 'New Course',
            'code' => 'ABC123',
            'slug' => 'new-course',
            'price' => 1000,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.instructor_id', (string) $instructor->id);
        $this->assertDatabaseHas('courses', ['code' => 'ABC123', 'instructor_id' => $instructor->id]);
    }

    public function test_store_with_uploaded_thumbnail_sets_thumbnail_url(): void
    {
        Storage::fake('public');

        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        Sanctum::actingAs($instructor);

        $response = $this->post('/api/courses', [
            'title' => 'New Course',
            'code' => 'ABC123',
            'slug' => 'new-course',
            'price' => 1000,
            'thumbnail' => UploadedFile::fake()->image('cover.jpg'),
        ], ['Accept' => 'application/json']);

        $response->assertStatus(201);
        $thumbnailUrl = $response->json('data.thumbnail_url');
        $this->assertNotEmpty($thumbnailUrl);
        $this->assertStringContainsString('course-thumbnails', $thumbnailUrl);
    }

    public function test_update_own_course_as_instructor_succeeds(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $instructor->id]);
        Sanctum::actingAs($instructor);

        // update()'s ownership check ($course->instructor_id === $request->user()->id) is
        // now reachable since the ScholarUser lookup correctly resolves the caller's role.
        $response = $this->patchJson("/api/courses/{$course->id}", [
            'instructor_id' => $course->instructor_id,
            'code' => $course->code,
            'title' => 'Updated Title',
            'slug' => $course->slug,
            'price' => $course->price,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.title', 'Updated Title');
        $this->assertDatabaseHas('courses', ['id' => $course->id, 'title' => 'Updated Title']);
    }

    public function test_update_with_uploaded_thumbnail_replaces_thumbnail_url(): void
    {
        Storage::fake('public');

        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $instructor->id]);
        Sanctum::actingAs($instructor);

        $response = $this->post("/api/courses/{$course->id}", [
            '_method' => 'PATCH',
            'instructor_id' => (string) $course->instructor_id,
            'code' => $course->code,
            'title' => $course->title,
            'slug' => $course->slug,
            'price' => $course->price,
            'thumbnail' => UploadedFile::fake()->image('new-cover.jpg'),
        ], ['Accept' => 'application/json']);

        $response->assertStatus(200);
        $thumbnailUrl = $response->json('data.thumbnail_url');
        $this->assertNotEmpty($thumbnailUrl);
        $this->assertStringContainsString('course-thumbnails', $thumbnailUrl);
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

    public function test_delete_own_course_as_instructor_succeeds(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $instructor->id]);
        Sanctum::actingAs($instructor);

        $response = $this->deleteJson("/api/courses/{$course->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('courses', ['id' => $course->id]);
    }

    public function test_delete_requires_authentication(): void
    {
        $course = Course::factory()->create();

        $response = $this->deleteJson("/api/courses/{$course->id}");

        $response->assertStatus(401);
    }
}

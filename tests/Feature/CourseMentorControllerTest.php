<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseMentor;
use App\Models\ScholarUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CourseMentorControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_is_public(): void
    {
        CourseMentor::factory()->count(2)->create();

        $response = $this->getJson('/api/course-mentors');

        $response->assertStatus(200)->assertJsonStructure(['status', 'data']);
    }

    public function test_index_filters_by_course_id(): void
    {
        $course = Course::factory()->create();
        $matching = CourseMentor::factory()->create(['course_id' => $course->id]);
        CourseMentor::factory()->create(); // different course

        $response = $this->getJson("/api/course-mentors?course_id={$course->id}");

        $response->assertStatus(200);
        $ids = collect($response->json('data.data'))->pluck('id');
        $this->assertCount(1, $ids);
        $this->assertTrue($ids->contains((string) $matching->id));
    }

    public function test_store_requires_authentication(): void
    {
        $course = Course::factory()->create();

        $response = $this->postJson('/api/course-mentors', [
            'course_id' => $course->id,
            'name' => 'Jane Doe',
        ]);

        $response->assertStatus(401);
    }

    public function test_store_as_owning_instructor_succeeds(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $instructor->id]);
        Sanctum::actingAs($instructor);

        $response = $this->postJson('/api/course-mentors', [
            'course_id' => $course->id,
            'name' => 'Jane Doe',
            'title' => 'Senior Software Engineer',
            'bio' => 'Ten years building backend systems.',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.name', 'Jane Doe');
        $this->assertDatabaseHas('course_mentors', ['course_id' => $course->id, 'name' => 'Jane Doe']);
    }

    public function test_store_as_non_owning_instructor_is_forbidden(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        $course = Course::factory()->create(); // owned by someone else
        Sanctum::actingAs($instructor);

        $response = $this->postJson('/api/course-mentors', [
            'course_id' => $course->id,
            'name' => 'Jane Doe',
        ]);

        $response->assertStatus(403);
    }

    public function test_store_as_student_is_forbidden(): void
    {
        $student = User::factory()->create();
        ScholarUser::factory()->create(['id' => $student->id, 'role' => 'student']);
        $course = Course::factory()->create();
        Sanctum::actingAs($student);

        $response = $this->postJson('/api/course-mentors', [
            'course_id' => $course->id,
            'name' => 'Jane Doe',
        ]);

        $response->assertStatus(403);
    }

    public function test_store_as_admin_succeeds_for_any_course(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        $course = Course::factory()->create();
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/course-mentors', [
            'course_id' => $course->id,
            'name' => 'Jane Doe',
        ]);

        $response->assertStatus(201);
    }

    public function test_store_rejects_second_mentor_for_same_course(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        $course = Course::factory()->create();
        CourseMentor::factory()->create(['course_id' => $course->id]);
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/course-mentors', [
            'course_id' => $course->id,
            'name' => 'Second Mentor',
        ]);

        $response->assertStatus(422);
    }

    public function test_show_is_public(): void
    {
        $courseMentor = CourseMentor::factory()->create(['name' => 'Jane Doe']);

        $response = $this->getJson("/api/course-mentors/{$courseMentor->id}");

        $response->assertStatus(200)->assertJsonPath('data.name', 'Jane Doe');
    }

    public function test_show_returns_404_for_missing_course_mentor(): void
    {
        $response = $this->getJson('/api/course-mentors/' . fake()->uuid());

        $response->assertStatus(404);
    }

    public function test_update_requires_authentication(): void
    {
        $courseMentor = CourseMentor::factory()->create();

        $response = $this->patchJson("/api/course-mentors/{$courseMentor->id}", [
            'course_id' => $courseMentor->course_id,
            'name' => 'Updated Name',
        ]);

        $response->assertStatus(401);
    }

    public function test_update_as_owning_instructor_succeeds(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $instructor->id]);
        $courseMentor = CourseMentor::factory()->create(['course_id' => $course->id, 'name' => 'Original Name']);
        Sanctum::actingAs($instructor);

        $response = $this->patchJson("/api/course-mentors/{$courseMentor->id}", [
            'course_id' => $course->id,
            'name' => 'Updated Name',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.name', 'Updated Name');
    }

    public function test_update_as_non_owning_instructor_is_forbidden(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        $courseMentor = CourseMentor::factory()->create(); // different course's mentor
        Sanctum::actingAs($instructor);

        $response = $this->patchJson("/api/course-mentors/{$courseMentor->id}", [
            'course_id' => $courseMentor->course_id,
            'name' => 'Updated Name',
        ]);

        $response->assertStatus(403);
    }

    public function test_delete_requires_authentication(): void
    {
        $courseMentor = CourseMentor::factory()->create();

        $response = $this->deleteJson("/api/course-mentors/{$courseMentor->id}");

        $response->assertStatus(401);
    }

    public function test_delete_as_owning_instructor_succeeds(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $instructor->id]);
        $courseMentor = CourseMentor::factory()->create(['course_id' => $course->id]);
        Sanctum::actingAs($instructor);

        $response = $this->deleteJson("/api/course-mentors/{$courseMentor->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('course_mentors', ['id' => $courseMentor->id]);
    }

    public function test_delete_as_non_owning_instructor_is_forbidden(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        $courseMentor = CourseMentor::factory()->create();
        Sanctum::actingAs($instructor);

        $response = $this->deleteJson("/api/course-mentors/{$courseMentor->id}");

        $response->assertStatus(403);
    }
}

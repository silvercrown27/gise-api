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

    public function test_index_requires_authentication(): void
    {
        $response = $this->getJson('/api/course-mentors');

        $response->assertStatus(401);
    }

    public function test_index_as_instructor_succeeds_scoped_to_own_mentorships(): void
    {
        // Instructors and admins can list (students are explicitly blocked); index()
        // scopes instructors to their own mentor_id rows.
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        CourseMentor::factory()->create(['mentor_id' => $instructor->id]);
        CourseMentor::factory()->create(); // someone else's mentorship
        Sanctum::actingAs($instructor);

        $response = $this->getJson('/api/course-mentors');

        $response->assertStatus(200);
        $ids = collect($response->json('data.data'))->pluck('mentor_id');
        $this->assertCount(1, $ids);
        $this->assertTrue($ids->contains((string) $instructor->id));
    }

    public function test_index_as_student_is_forbidden(): void
    {
        $student = User::factory()->create();
        ScholarUser::factory()->create(['id' => $student->id, 'role' => 'student']);
        Sanctum::actingAs($student);

        $response = $this->getJson('/api/course-mentors');

        $response->assertStatus(403);
    }

    public function test_store_requires_authentication(): void
    {
        $course = Course::factory()->create();
        $mentor = User::factory()->create();

        $response = $this->postJson('/api/course-mentors', [
            'course_id' => $course->id,
            'mentor_id' => $mentor->id,
        ]);

        $response->assertStatus(401);
    }

    public function test_store_as_admin_succeeds(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        $course = Course::factory()->create();
        $mentor = User::factory()->create();
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/course-mentors', [
            'course_id' => $course->id,
            'mentor_id' => $mentor->id,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.mentor_id', (string) $mentor->id);
        $this->assertDatabaseHas('course_mentors', ['course_id' => $course->id, 'mentor_id' => $mentor->id]);
    }

    public function test_store_as_student_is_forbidden(): void
    {
        $student = User::factory()->create();
        ScholarUser::factory()->create(['id' => $student->id, 'role' => 'student']);
        $course = Course::factory()->create();
        $mentor = User::factory()->create();
        Sanctum::actingAs($student);

        $response = $this->postJson('/api/course-mentors', [
            'course_id' => $course->id,
            'mentor_id' => $mentor->id,
        ]);

        $response->assertStatus(403);
    }

    public function test_show_requires_authentication(): void
    {
        $courseMentor = CourseMentor::factory()->create();

        $response = $this->getJson("/api/course-mentors/{$courseMentor->id}");

        $response->assertStatus(401);
    }

    public function test_show_returns_404_for_missing_course_mentor(): void
    {
        $admin = User::factory()->create();
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/course-mentors/' . fake()->uuid());

        $response->assertStatus(404);
    }

    public function test_show_own_mentorship_succeeds(): void
    {
        // Fixed: show() now casts both sides to string before comparing, so the
        // Ramsey\Uuid object vs. plain string mismatch no longer blocks the genuine
        // owner from viewing their own row.
        $mentor = User::factory()->create();
        $courseMentor = CourseMentor::factory()->create(['mentor_id' => $mentor->id]);
        Sanctum::actingAs($mentor);

        $response = $this->getJson("/api/course-mentors/{$courseMentor->id}");

        $response->assertStatus(200);
    }

    public function test_show_other_users_mentorship_is_forbidden(): void
    {
        $mentor = User::factory()->create();
        $otherUser = User::factory()->create();
        $courseMentor = CourseMentor::factory()->create(['mentor_id' => $mentor->id]);
        Sanctum::actingAs($otherUser);

        $response = $this->getJson("/api/course-mentors/{$courseMentor->id}");

        $response->assertStatus(403);
    }

    public function test_update_requires_authentication(): void
    {
        $courseMentor = CourseMentor::factory()->create();

        $response = $this->patchJson("/api/course-mentors/{$courseMentor->id}", [
            'course_id' => $courseMentor->course_id,
            'mentor_id' => $courseMentor->mentor_id,
        ]);

        $response->assertStatus(401);
    }

    public function test_update_as_admin_succeeds(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        $courseMentor = CourseMentor::factory()->create();
        $newMentor = User::factory()->create();
        Sanctum::actingAs($admin);

        $response = $this->patchJson("/api/course-mentors/{$courseMentor->id}", [
            'course_id' => $courseMentor->course_id,
            'mentor_id' => $newMentor->id,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.mentor_id', (string) $newMentor->id);
        $this->assertDatabaseHas('course_mentors', ['id' => $courseMentor->id, 'mentor_id' => $newMentor->id]);
    }

    public function test_delete_requires_authentication(): void
    {
        $courseMentor = CourseMentor::factory()->create();

        $response = $this->deleteJson("/api/course-mentors/{$courseMentor->id}");

        $response->assertStatus(401);
    }

    public function test_delete_as_admin_succeeds(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        $courseMentor = CourseMentor::factory()->create();
        Sanctum::actingAs($admin);

        $response = $this->deleteJson("/api/course-mentors/{$courseMentor->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('course_mentors', ['id' => $courseMentor->id]);
    }
}

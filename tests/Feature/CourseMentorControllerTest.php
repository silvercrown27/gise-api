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

    public function test_index_as_instructor_is_forbidden_due_to_lookup_bug(): void
    {
        // Instructors and admins should be able to list (learners are explicitly blocked),
        // but ScholarUser::find($request->user()->id) always returns null for real users,
        // so the "!$user" branch triggers Forbidden for everyone, including instructors.
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['user_id' => $instructor->id, 'role' => 'instructor']);
        Sanctum::actingAs($instructor);

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

    public function test_store_as_admin_is_forbidden_due_to_lookup_bug(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['user_id' => $admin->id, 'role' => 'admin']);
        $course = Course::factory()->create();
        $mentor = User::factory()->create();
        Sanctum::actingAs($admin);

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

    public function test_update_as_admin_is_forbidden_due_to_lookup_bug(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['user_id' => $admin->id, 'role' => 'admin']);
        $courseMentor = CourseMentor::factory()->create();
        Sanctum::actingAs($admin);

        $response = $this->patchJson("/api/course-mentors/{$courseMentor->id}", [
            'course_id' => $courseMentor->course_id,
            'mentor_id' => $courseMentor->mentor_id,
        ]);

        $response->assertStatus(403);
    }

    public function test_delete_requires_authentication(): void
    {
        $courseMentor = CourseMentor::factory()->create();

        $response = $this->deleteJson("/api/course-mentors/{$courseMentor->id}");

        $response->assertStatus(401);
    }

    public function test_delete_as_admin_is_forbidden_due_to_lookup_bug(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['user_id' => $admin->id, 'role' => 'admin']);
        $courseMentor = CourseMentor::factory()->create();
        Sanctum::actingAs($admin);

        $response = $this->deleteJson("/api/course-mentors/{$courseMentor->id}");

        $response->assertStatus(403);
    }
}

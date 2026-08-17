<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Exam;
use App\Models\ScholarUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExamControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_requires_authentication(): void
    {
        $response = $this->getJson('/api/exams');

        $response->assertStatus(401);
    }

    public function test_index_as_unresolvable_user_returns_empty_results(): void
    {
        // index() doesn't hard-reject; when $user is null (lookup bug) and role isn't
        // 'admin', it falls into the "elseif" branch which forces a where('id', null)
        // filter, silently returning an empty paginated list instead of 403.
        $user = User::factory()->create();
        Exam::factory()->count(2)->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/exams');

        $response->assertStatus(200);
        $this->assertCount(0, $response->json('data.data'));
    }

    public function test_store_requires_authentication(): void
    {
        $course = Course::factory()->create();
        $creator = User::factory()->create();

        $response = $this->postJson('/api/exams', [
            'course_id' => $course->id,
            'created_by' => $creator->id,
            'title' => 'Midterm',
            'total_marks' => 100,
            'passing_marks' => 50,
        ]);

        $response->assertStatus(401);
    }

    public function test_store_as_instructor_is_forbidden_due_to_lookup_bug(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['user_id' => $instructor->id, 'role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $instructor->id]);
        Sanctum::actingAs($instructor);

        $response = $this->postJson('/api/exams', [
            'course_id' => $course->id,
            'created_by' => $instructor->id,
            'title' => 'Midterm',
            'total_marks' => 100,
            'passing_marks' => 50,
        ]);

        $response->assertStatus(403);
    }

    public function test_show_requires_authentication(): void
    {
        $exam = Exam::factory()->create();

        $response = $this->getJson("/api/exams/{$exam->id}");

        $response->assertStatus(401);
    }

    public function test_show_returns_404_for_missing_exam(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/exams/' . fake()->uuid());

        $response->assertStatus(404);
    }

    public function test_show_as_unresolvable_user_is_forbidden(): void
    {
        // show() explicitly rejects unless admin, owning instructor, or role === 'learner'.
        // Since ScholarUser::find always returns null for real users, $user is null and
        // the final `(!$user || $user->role !== 'learner')` clause evaluates true, so even
        // learners are rejected here.
        $learner = User::factory()->create();
        $exam = Exam::factory()->create();
        Sanctum::actingAs($learner);

        $response = $this->getJson("/api/exams/{$exam->id}");

        $response->assertStatus(403);
    }

    public function test_update_requires_authentication(): void
    {
        $exam = Exam::factory()->create();

        $response = $this->patchJson("/api/exams/{$exam->id}", ['title' => 'Updated']);

        $response->assertStatus(401);
    }

    public function test_update_as_owning_instructor_is_forbidden_due_to_lookup_bug(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['user_id' => $instructor->id, 'role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $instructor->id]);
        $exam = Exam::factory()->create(['course_id' => $course->id]);
        Sanctum::actingAs($instructor);

        $response = $this->patchJson("/api/exams/{$exam->id}", [
            'course_id' => $course->id,
            'created_by' => $instructor->id,
            'title' => 'Updated Title',
            'total_marks' => 100,
            'passing_marks' => 50,
        ]);

        $response->assertStatus(403);
    }

    public function test_delete_requires_authentication(): void
    {
        $exam = Exam::factory()->create();

        $response = $this->deleteJson("/api/exams/{$exam->id}");

        $response->assertStatus(401);
    }

    public function test_delete_as_owning_instructor_is_forbidden_due_to_lookup_bug(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['user_id' => $instructor->id, 'role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $instructor->id]);
        $exam = Exam::factory()->create(['course_id' => $course->id]);
        Sanctum::actingAs($instructor);

        $response = $this->deleteJson("/api/exams/{$exam->id}");

        $response->assertStatus(403);
    }
}

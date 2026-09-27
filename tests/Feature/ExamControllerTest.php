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

    public function test_store_as_instructor_succeeds(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $instructor->id]);
        Sanctum::actingAs($instructor);

        $response = $this->postJson('/api/exams', [
            'course_id' => $course->id,
            'created_by' => $instructor->id,
            'title' => 'Midterm',
            'total_marks' => 100,
            'passing_marks' => 50,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.title', 'Midterm');
        $this->assertDatabaseHas('exams', ['course_id' => $course->id, 'title' => 'Midterm']);
    }

    public function test_store_as_instructor_starts_pending_and_notifies_admins(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'super_admin']);
        $course = Course::factory()->create(['instructor_id' => $instructor->id]);
        Sanctum::actingAs($instructor);

        $response = $this->postJson('/api/exams', [
            'course_id' => $course->id,
            'created_by' => $instructor->id,
            'title' => 'Midterm',
            'total_marks' => 100,
            'passing_marks' => 50,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.admin_approval_status', 'pending');
        $this->assertDatabaseHas('notifications', [
            'user_id' => $admin->id,
            'type' => 'exam_review',
        ]);
    }

    public function test_store_as_admin_is_auto_approved(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'super_admin']);
        $course = Course::factory()->create();
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/exams', [
            'course_id' => $course->id,
            'created_by' => $admin->id,
            'title' => 'Midterm',
            'total_marks' => 100,
            'passing_marks' => 50,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.admin_approval_status', 'approved');
    }

    public function test_store_as_non_owning_instructor_is_forbidden(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        $course = Course::factory()->create(); // owned by a different instructor
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

    public function test_store_as_student_is_forbidden(): void
    {
        $student = User::factory()->create();
        ScholarUser::factory()->create(['id' => $student->id, 'role' => 'student']);
        $course = Course::factory()->create();
        Sanctum::actingAs($student);

        $response = $this->postJson('/api/exams', [
            'course_id' => $course->id,
            'created_by' => $student->id,
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

    public function test_show_exposes_correct_answer_to_the_owning_instructor(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $instructor->id]);
        $exam = Exam::factory()->create(['course_id' => $course->id]);
        \App\Models\ExamQuestion::factory()->create(['exam_id' => $exam->id, 'correct_answer' => 'a']);
        Sanctum::actingAs($instructor);

        $response = $this->getJson("/api/exams/{$exam->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.questions.0.correct_answer', 'a');
    }

    public function test_update_requires_authentication(): void
    {
        $exam = Exam::factory()->create();

        $response = $this->patchJson("/api/exams/{$exam->id}", ['title' => 'Updated']);

        $response->assertStatus(401);
    }

    public function test_update_as_owning_instructor_succeeds(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
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

        $response->assertStatus(200);
        $response->assertJsonPath('data.title', 'Updated Title');
        $this->assertDatabaseHas('exams', ['id' => $exam->id, 'title' => 'Updated Title']);
    }

    public function test_update_as_non_owning_instructor_is_forbidden(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        $exam = Exam::factory()->create(); // belongs to a different course/instructor
        Sanctum::actingAs($instructor);

        $response = $this->patchJson("/api/exams/{$exam->id}", [
            'course_id' => $exam->course_id,
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

    public function test_delete_as_owning_instructor_succeeds(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $instructor->id]);
        $exam = Exam::factory()->create(['course_id' => $course->id]);
        Sanctum::actingAs($instructor);

        $response = $this->deleteJson("/api/exams/{$exam->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('exams', ['id' => $exam->id]);
    }

    public function test_delete_as_non_owning_instructor_is_forbidden(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        $exam = Exam::factory()->create(); // belongs to a different course/instructor
        Sanctum::actingAs($instructor);

        $response = $this->deleteJson("/api/exams/{$exam->id}");

        $response->assertStatus(403);
    }

    public function test_update_as_instructor_resets_an_approved_exam_to_pending(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $instructor->id]);
        $exam = Exam::factory()->create(['course_id' => $course->id, 'admin_approval_status' => 'approved']);
        Sanctum::actingAs($instructor);

        $response = $this->patchJson("/api/exams/{$exam->id}", [
            'course_id' => $course->id,
            'created_by' => $instructor->id,
            'title' => 'Updated Title',
            'total_marks' => 100,
            'passing_marks' => 50,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.admin_approval_status', 'pending');
    }

    public function test_update_as_admin_stays_approved(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'super_admin']);
        $exam = Exam::factory()->create(['admin_approval_status' => 'pending']);
        Sanctum::actingAs($admin);

        $response = $this->patchJson("/api/exams/{$exam->id}", [
            'course_id' => $exam->course_id,
            'created_by' => $exam->created_by,
            'title' => 'Updated Title',
            'total_marks' => 100,
            'passing_marks' => 50,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.admin_approval_status', 'approved');
    }

    public function test_for_review_requires_admin(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        Sanctum::actingAs($instructor);

        $response = $this->getJson('/api/exams/for-review');

        $response->assertStatus(403);
    }

    public function test_for_review_as_admin_filters_by_approval_status(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        Exam::factory()->create(['admin_approval_status' => 'pending', 'title' => 'Pending exam']);
        Exam::factory()->create(['admin_approval_status' => 'approved', 'title' => 'Approved exam']);
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/exams/for-review?admin_approval_status=pending');

        $response->assertStatus(200);
        $titles = collect($response->json('data.data'))->pluck('title');
        $this->assertTrue($titles->contains('Pending exam'));
        $this->assertFalse($titles->contains('Approved exam'));
    }

    public function test_set_approval_status_requires_admin(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        $exam = Exam::factory()->create();
        Sanctum::actingAs($instructor);

        $response = $this->patchJson("/api/exams/{$exam->id}/approval-status", ['admin_approval_status' => 'approved']);

        $response->assertStatus(403);
    }

    public function test_set_approval_status_approve_notifies_instructor_and_logs_audit(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'super_admin']);
        $instructor = User::factory()->create();
        $course = Course::factory()->create(['instructor_id' => $instructor->id]);
        $exam = Exam::factory()->create(['course_id' => $course->id, 'admin_approval_status' => 'pending']);
        Sanctum::actingAs($admin);

        $response = $this->patchJson("/api/exams/{$exam->id}/approval-status", ['admin_approval_status' => 'approved']);

        $response->assertStatus(200);
        $response->assertJsonPath('data.admin_approval_status', 'approved');
        $this->assertDatabaseHas('exams', ['id' => $exam->id, 'admin_approval_status' => 'approved']);
        $this->assertDatabaseHas('admin_audit_logs', [
            'admin_id' => $admin->id,
            'action' => 'approve_exam',
            'target_type' => 'exam',
            'target_id' => $exam->id,
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $instructor->id,
            'type' => 'exam_review',
        ]);
    }

    public function test_set_approval_status_reject_stores_reason(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'super_admin']);
        $exam = Exam::factory()->create(['admin_approval_status' => 'pending']);
        Sanctum::actingAs($admin);

        $response = $this->patchJson("/api/exams/{$exam->id}/approval-status", [
            'admin_approval_status' => 'rejected',
            'admin_rejection_reason' => 'Missing rubric.',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.admin_approval_status', 'rejected');
        $response->assertJsonPath('data.admin_rejection_reason', 'Missing rubric.');
    }

    public function test_set_approval_status_rejects_invalid_status(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'super_admin']);
        $exam = Exam::factory()->create();
        Sanctum::actingAs($admin);

        $response = $this->patchJson("/api/exams/{$exam->id}/approval-status", ['admin_approval_status' => 'bogus']);

        $response->assertStatus(422);
    }
}

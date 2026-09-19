<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseModule;
use App\Models\ModuleQuiz;
use App\Models\ScholarUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ModuleQuizControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_is_public(): void
    {
        ModuleQuiz::factory()->count(2)->create();

        $response = $this->getJson('/api/module-quizzes');

        $response->assertStatus(200)->assertJsonStructure(['status', 'data']);
    }

    public function test_index_filters_by_module_id(): void
    {
        $module = CourseModule::factory()->create();
        $matching = ModuleQuiz::factory()->create(['module_id' => $module->id]);
        ModuleQuiz::factory()->create(); // different module

        $response = $this->getJson("/api/module-quizzes?module_id={$module->id}");

        $response->assertStatus(200);
        $ids = collect($response->json('data.data'))->pluck('id');
        $this->assertCount(1, $ids);
        $this->assertTrue($ids->contains((string) $matching->id));
    }

    public function test_show_is_public(): void
    {
        $quiz = ModuleQuiz::factory()->create();

        $response = $this->getJson("/api/module-quizzes/{$quiz->id}");

        $response->assertStatus(200)->assertJsonPath('data.id', (string) $quiz->id);
    }

    public function test_show_hides_correct_option_key_for_unauthenticated_visitor(): void
    {
        $quiz = ModuleQuiz::factory()->create();
        \App\Models\ModuleQuizQuestion::factory()->create([
            'quiz_id' => $quiz->id,
            'options' => ['a' => 'A', 'b' => 'B'],
            'correct_option_key' => 'a',
        ]);

        $response = $this->getJson("/api/module-quizzes/{$quiz->id}");

        $response->assertStatus(200);
        $this->assertArrayNotHasKey('correct_option_key', $response->json('data.questions.0'));
    }

    public function test_show_hides_correct_option_key_for_students(): void
    {
        $quiz = ModuleQuiz::factory()->create();
        \App\Models\ModuleQuizQuestion::factory()->create([
            'quiz_id' => $quiz->id,
            'options' => ['a' => 'A', 'b' => 'B'],
            'correct_option_key' => 'a',
        ]);

        $student = User::factory()->create();
        ScholarUser::factory()->create(['id' => $student->id, 'role' => 'student']);
        Sanctum::actingAs($student);

        $response = $this->getJson("/api/module-quizzes/{$quiz->id}");

        $response->assertStatus(200);
        $this->assertArrayNotHasKey('correct_option_key', $response->json('data.questions.0'));
    }

    public function test_show_reveals_correct_option_key_for_admin(): void
    {
        $quiz = ModuleQuiz::factory()->create();
        \App\Models\ModuleQuizQuestion::factory()->create([
            'quiz_id' => $quiz->id,
            'options' => ['a' => 'A', 'b' => 'B'],
            'correct_option_key' => 'a',
        ]);

        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        Sanctum::actingAs($admin);

        $response = $this->getJson("/api/module-quizzes/{$quiz->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.questions.0.correct_option_key', 'a');
    }

    public function test_show_returns_404_for_missing_quiz(): void
    {
        $response = $this->getJson('/api/module-quizzes/' . fake()->uuid());

        $response->assertStatus(404);
    }

    public function test_store_requires_authentication(): void
    {
        $module = CourseModule::factory()->create();

        $response = $this->postJson('/api/module-quizzes', [
            'module_id' => $module->id,
            'title' => 'Module 1 quiz',
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

        $response = $this->postJson('/api/module-quizzes', [
            'module_id' => $module->id,
            'title' => 'Module 1 quiz',
            'passing_percent' => 70,
            'max_attempts' => 3,
            'cooldown_hours' => 24,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.title', 'Module 1 quiz');
        $this->assertDatabaseHas('module_quizzes', ['module_id' => $module->id, 'title' => 'Module 1 quiz']);
    }

    public function test_store_as_non_owning_instructor_is_forbidden(): void
    {
        $owner = User::factory()->create();
        ScholarUser::factory()->create(['id' => $owner->id, 'role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $owner->id]);
        $module = CourseModule::factory()->create(['course_id' => $course->id]);

        $otherInstructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $otherInstructor->id, 'role' => 'instructor']);
        Sanctum::actingAs($otherInstructor);

        $response = $this->postJson('/api/module-quizzes', [
            'module_id' => $module->id,
            'title' => 'Module 1 quiz',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('module_quizzes', ['module_id' => $module->id]);
    }

    public function test_store_as_instructor_sets_pending_and_notifies_admins(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);

        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $instructor->id]);
        $module = CourseModule::factory()->create(['course_id' => $course->id]);
        Sanctum::actingAs($instructor);

        $response = $this->postJson('/api/module-quizzes', [
            'module_id' => $module->id,
            'title' => 'Module 1 quiz',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.admin_approval_status', 'pending');
        $this->assertDatabaseHas('notifications', ['user_id' => $admin->id, 'type' => 'quiz_review']);
    }

    public function test_store_as_admin_sets_approved(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        $module = CourseModule::factory()->create();
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/module-quizzes', [
            'module_id' => $module->id,
            'title' => 'Module 1 quiz',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.admin_approval_status', 'approved');
    }

    public function test_store_as_student_is_forbidden(): void
    {
        $student = User::factory()->create();
        ScholarUser::factory()->create(['id' => $student->id, 'role' => 'student']);
        $module = CourseModule::factory()->create();
        Sanctum::actingAs($student);

        $response = $this->postJson('/api/module-quizzes', [
            'module_id' => $module->id,
            'title' => 'Module 1 quiz',
        ]);

        $response->assertStatus(403);
    }

    public function test_store_validation_failure_returns_422(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/module-quizzes', []);

        $response->assertStatus(422);
    }

    public function test_update_requires_authentication(): void
    {
        $quiz = ModuleQuiz::factory()->create();

        $response = $this->patchJson("/api/module-quizzes/{$quiz->id}", ['title' => 'Updated']);

        $response->assertStatus(401);
    }

    public function test_update_as_admin_succeeds(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        $quiz = ModuleQuiz::factory()->create();
        Sanctum::actingAs($admin);

        $response = $this->patchJson("/api/module-quizzes/{$quiz->id}", [
            'module_id' => $quiz->module_id,
            'title' => 'Updated quiz',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.title', 'Updated quiz');
    }

    public function test_update_as_instructor_resets_to_pending_even_if_previously_approved(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);

        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $instructor->id]);
        $module = CourseModule::factory()->create(['course_id' => $course->id]);
        $quiz = ModuleQuiz::factory()->create(['module_id' => $module->id, 'admin_approval_status' => 'approved']);
        Sanctum::actingAs($instructor);

        $response = $this->patchJson("/api/module-quizzes/{$quiz->id}", [
            'module_id' => $module->id,
            'title' => 'Tweaked title',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.admin_approval_status', 'pending');
        $this->assertDatabaseHas('notifications', ['user_id' => $admin->id, 'type' => 'quiz_review']);
    }

    public function test_update_as_admin_keeps_approved(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        $quiz = ModuleQuiz::factory()->create(['admin_approval_status' => 'pending']);
        Sanctum::actingAs($admin);

        $response = $this->patchJson("/api/module-quizzes/{$quiz->id}", [
            'module_id' => $quiz->module_id,
            'title' => 'Fixed by admin',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.admin_approval_status', 'approved');
    }

    public function test_update_as_owning_instructor_succeeds(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $instructor->id]);
        $module = CourseModule::factory()->create(['course_id' => $course->id]);
        $quiz = ModuleQuiz::factory()->create(['module_id' => $module->id]);
        Sanctum::actingAs($instructor);

        $response = $this->patchJson("/api/module-quizzes/{$quiz->id}", [
            'module_id' => $module->id,
            'title' => 'Updated by owner',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.title', 'Updated by owner');
    }

    public function test_update_as_non_owning_instructor_is_forbidden(): void
    {
        $owner = User::factory()->create();
        ScholarUser::factory()->create(['id' => $owner->id, 'role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $owner->id]);
        $module = CourseModule::factory()->create(['course_id' => $course->id]);
        $quiz = ModuleQuiz::factory()->create(['module_id' => $module->id, 'title' => 'Original title']);

        $otherInstructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $otherInstructor->id, 'role' => 'instructor']);
        Sanctum::actingAs($otherInstructor);

        $response = $this->patchJson("/api/module-quizzes/{$quiz->id}", [
            'module_id' => $module->id,
            'title' => 'Hijacked title',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseHas('module_quizzes', ['id' => $quiz->id, 'title' => 'Original title']);
    }

    public function test_update_as_non_owning_instructor_cannot_reparent_by_changing_module_id(): void
    {
        $owner = User::factory()->create();
        ScholarUser::factory()->create(['id' => $owner->id, 'role' => 'instructor']);
        $ownerCourse = Course::factory()->create(['instructor_id' => $owner->id]);
        $ownerModule = CourseModule::factory()->create(['course_id' => $ownerCourse->id]);
        $quiz = ModuleQuiz::factory()->create(['module_id' => $ownerModule->id]);

        $attacker = User::factory()->create();
        ScholarUser::factory()->create(['id' => $attacker->id, 'role' => 'instructor']);
        $attackerCourse = Course::factory()->create(['instructor_id' => $attacker->id]);
        $attackerModule = CourseModule::factory()->create(['course_id' => $attackerCourse->id]);
        Sanctum::actingAs($attacker);

        $response = $this->patchJson("/api/module-quizzes/{$quiz->id}", [
            'module_id' => $attackerModule->id,
            'title' => 'Reparented',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseHas('module_quizzes', ['id' => $quiz->id, 'module_id' => $ownerModule->id]);
    }

    public function test_delete_as_non_owning_instructor_is_forbidden(): void
    {
        $owner = User::factory()->create();
        ScholarUser::factory()->create(['id' => $owner->id, 'role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $owner->id]);
        $module = CourseModule::factory()->create(['course_id' => $course->id]);
        $quiz = ModuleQuiz::factory()->create(['module_id' => $module->id]);

        $otherInstructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $otherInstructor->id, 'role' => 'instructor']);
        Sanctum::actingAs($otherInstructor);

        $response = $this->deleteJson("/api/module-quizzes/{$quiz->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('module_quizzes', ['id' => $quiz->id]);
    }

    public function test_delete_requires_authentication(): void
    {
        $quiz = ModuleQuiz::factory()->create();

        $response = $this->deleteJson("/api/module-quizzes/{$quiz->id}");

        $response->assertStatus(401);
    }

    public function test_delete_as_admin_succeeds(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        $quiz = ModuleQuiz::factory()->create();
        Sanctum::actingAs($admin);

        $response = $this->deleteJson("/api/module-quizzes/{$quiz->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('module_quizzes', ['id' => $quiz->id]);
    }

    public function test_set_approval_status_requires_authentication(): void
    {
        $quiz = ModuleQuiz::factory()->create(['admin_approval_status' => 'pending']);

        $response = $this->patchJson("/api/module-quizzes/{$quiz->id}/approval-status", [
            'admin_approval_status' => 'approved',
        ]);

        $response->assertStatus(401);
    }

    public function test_set_approval_status_as_instructor_is_forbidden(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        $quiz = ModuleQuiz::factory()->create(['admin_approval_status' => 'pending']);
        Sanctum::actingAs($instructor);

        $response = $this->patchJson("/api/module-quizzes/{$quiz->id}/approval-status", [
            'admin_approval_status' => 'approved',
        ]);

        $response->assertStatus(403);
    }

    public function test_set_approval_status_rejects_invalid_value(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        $quiz = ModuleQuiz::factory()->create(['admin_approval_status' => 'pending']);
        Sanctum::actingAs($admin);

        $response = $this->patchJson("/api/module-quizzes/{$quiz->id}/approval-status", [
            'admin_approval_status' => 'not-a-real-status',
        ]);

        $response->assertStatus(422);
    }

    public function test_set_approval_status_as_admin_approves_and_notifies_instructor(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);

        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $instructor->id]);
        $module = CourseModule::factory()->create(['course_id' => $course->id]);
        $quiz = ModuleQuiz::factory()->create(['module_id' => $module->id, 'admin_approval_status' => 'pending']);
        Sanctum::actingAs($admin);

        $response = $this->patchJson("/api/module-quizzes/{$quiz->id}/approval-status", [
            'admin_approval_status' => 'approved',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.admin_approval_status', 'approved');
        $this->assertDatabaseHas('admin_audit_logs', [
            'admin_id' => $admin->id,
            'action' => 'approve_quiz',
            'target_type' => 'module_quiz',
            'target_id' => $quiz->id,
        ]);
        $this->assertDatabaseHas('notifications', ['user_id' => $instructor->id, 'type' => 'quiz_review']);
    }

    public function test_set_approval_status_as_admin_rejects_with_reason_and_notifies_instructor(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);

        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $instructor->id]);
        $module = CourseModule::factory()->create(['course_id' => $course->id]);
        $quiz = ModuleQuiz::factory()->create(['module_id' => $module->id, 'admin_approval_status' => 'pending']);
        Sanctum::actingAs($admin);

        $response = $this->patchJson("/api/module-quizzes/{$quiz->id}/approval-status", [
            'admin_approval_status' => 'rejected',
            'admin_rejection_reason' => 'Question 2 has no correct answer marked.',
        ]);

        $response->assertStatus(200);
        $quiz->refresh();
        $this->assertSame('rejected', $quiz->admin_approval_status);
        $this->assertSame('Question 2 has no correct answer marked.', $quiz->admin_rejection_reason);
        $this->assertDatabaseHas('notifications', ['user_id' => $instructor->id, 'type' => 'quiz_review']);
    }

    public function test_set_approval_status_reset_to_pending(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        $quiz = ModuleQuiz::factory()->create(['admin_approval_status' => 'approved']);
        Sanctum::actingAs($admin);

        $response = $this->patchJson("/api/module-quizzes/{$quiz->id}/approval-status", [
            'admin_approval_status' => 'pending',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('admin_audit_logs', [
            'admin_id' => $admin->id,
            'action' => 'reset_quiz_approval',
            'target_type' => 'module_quiz',
            'target_id' => $quiz->id,
        ]);
    }

    public function test_for_review_requires_authentication(): void
    {
        $response = $this->getJson('/api/module-quizzes/for-review');

        $response->assertStatus(401);
    }

    public function test_for_review_as_instructor_is_forbidden(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        Sanctum::actingAs($instructor);

        $response = $this->getJson('/api/module-quizzes/for-review');

        $response->assertStatus(403);
    }

    public function test_for_review_filters_by_admin_approval_status(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        ModuleQuiz::factory()->create(['admin_approval_status' => 'pending']);
        ModuleQuiz::factory()->create(['admin_approval_status' => 'approved']);
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/module-quizzes/for-review?admin_approval_status=pending');

        $response->assertStatus(200);
        $statuses = collect($response->json('data.data'))->pluck('admin_approval_status');
        $this->assertTrue($statuses->every(fn ($s) => $s === 'pending'));
    }
}

<?php

namespace Tests\Feature;

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
        $module = CourseModule::factory()->create();
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
}

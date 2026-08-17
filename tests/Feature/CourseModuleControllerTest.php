<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseModule;
use App\Models\ScholarUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CourseModuleControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_is_public(): void
    {
        CourseModule::factory()->count(2)->create();

        $response = $this->getJson('/api/course-modules');

        $response->assertStatus(200)->assertJsonStructure(['status', 'data']);
    }

    public function test_show_is_public(): void
    {
        $module = CourseModule::factory()->create();

        $response = $this->getJson("/api/course-modules/{$module->id}");

        $response->assertStatus(200)->assertJsonPath('data.id', (string) $module->id);
    }

    public function test_show_returns_404_for_missing_module(): void
    {
        $response = $this->getJson('/api/course-modules/' . fake()->uuid());

        $response->assertStatus(404);
    }

    public function test_store_requires_authentication(): void
    {
        $course = Course::factory()->create();

        $response = $this->postJson('/api/course-modules', [
            'course_id' => $course->id,
            'title' => 'Module 1',
        ]);

        $response->assertStatus(401);
    }

    public function test_store_as_instructor_succeeds(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $instructor->id]);
        Sanctum::actingAs($instructor);

        $response = $this->postJson('/api/course-modules', [
            'course_id' => $course->id,
            'title' => 'Module 1',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.title', 'Module 1');
        $this->assertDatabaseHas('course_modules', ['course_id' => $course->id, 'title' => 'Module 1']);
    }

    public function test_store_as_student_is_forbidden(): void
    {
        $student = User::factory()->create();
        ScholarUser::factory()->create(['id' => $student->id, 'role' => 'student']);
        $course = Course::factory()->create();
        Sanctum::actingAs($student);

        $response = $this->postJson('/api/course-modules', [
            'course_id' => $course->id,
            'title' => 'Module 1',
        ]);

        $response->assertStatus(403);
    }

    public function test_update_requires_authentication(): void
    {
        $module = CourseModule::factory()->create();

        $response = $this->patchJson("/api/course-modules/{$module->id}", ['title' => 'X']);

        $response->assertStatus(401);
    }

    public function test_update_as_admin_succeeds(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        $module = CourseModule::factory()->create();
        Sanctum::actingAs($admin);

        $response = $this->patchJson("/api/course-modules/{$module->id}", [
            'course_id' => $module->course_id,
            'title' => 'Updated',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.title', 'Updated');
        $this->assertDatabaseHas('course_modules', ['id' => $module->id, 'title' => 'Updated']);
    }

    public function test_delete_requires_authentication(): void
    {
        $module = CourseModule::factory()->create();

        $response = $this->deleteJson("/api/course-modules/{$module->id}");

        $response->assertStatus(401);
    }

    public function test_delete_as_admin_succeeds(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        $module = CourseModule::factory()->create();
        Sanctum::actingAs($admin);

        $response = $this->deleteJson("/api/course-modules/{$module->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('course_modules', ['id' => $module->id]);
    }
}

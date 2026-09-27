<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseModule;
use App\Models\InstructorProfile;
use App\Models\ScholarUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RoleAndApprovalTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        ScholarUser::factory()->create(['id' => $user->id, 'role' => $role]);

        return $user;
    }

    public function test_super_admin_changes_roles_and_instructor_promotion_is_approved(): void
    {
        $super = $this->userWithRole('super_admin');
        $target = $this->userWithRole('student');
        Sanctum::actingAs($super);

        $this->patchJson("/api/scholar-users/{$target->id}/role", ['role' => 'instructor'])->assertStatus(200);
        $this->assertSame('instructor', ScholarUser::find($target->id)->role);
        $this->assertSame('approved', InstructorProfile::where('user_id', $target->id)->value('approval_status'));

        $this->patchJson("/api/scholar-users/{$target->id}/role", ['role' => 'admin'])->assertStatus(200);
        $this->patchJson("/api/scholar-users/{$target->id}/role", ['role' => 'super_admin'])->assertStatus(200);
        $this->patchJson("/api/scholar-users/{$target->id}/role", ['role' => 'student'])->assertStatus(200);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'change_role', 'target_id' => $target->id]);

        $this->patchJson("/api/scholar-users/{$target->id}/role", ['role' => 'owner'])->assertStatus(422);
    }

    public function test_admin_cannot_change_roles(): void
    {
        $target = $this->userWithRole('student');
        Sanctum::actingAs($this->userWithRole('admin'));

        $this->patchJson("/api/scholar-users/{$target->id}/role", ['role' => 'admin'])->assertStatus(403);
    }

    public function test_the_last_super_admin_cannot_be_demoted(): void
    {
        $super = $this->userWithRole('super_admin');
        Sanctum::actingAs($super);

        $this->patchJson("/api/scholar-users/{$super->id}/role", ['role' => 'admin'])->assertStatus(422);

        $second = $this->userWithRole('super_admin');
        $this->patchJson("/api/scholar-users/{$second->id}/role", ['role' => 'admin'])->assertStatus(200);
    }

    public function test_admin_can_author_content_but_it_waits_for_a_super_admin(): void
    {
        $super = $this->userWithRole('super_admin');
        $admin = $this->userWithRole('admin');
        $course = Course::factory()->create();
        Sanctum::actingAs($admin);

        $moduleId = $this->postJson('/api/course-modules', ['course_id' => $course->id, 'title' => 'Admin module'])
            ->assertStatus(201)
            ->assertJsonPath('data.admin_approval_status', 'pending')
            ->json('data.id');

        $this->assertDatabaseHas('notifications', ['user_id' => $super->id, 'type' => 'module_review']);
        $this->assertDatabaseMissing('notifications', ['user_id' => $admin->id, 'type' => 'module_review']);

        // Admins can't approve - even their own work.
        $this->patchJson("/api/course-modules/{$moduleId}/approval-status", ['admin_approval_status' => 'approved'])
            ->assertStatus(403);

        Sanctum::actingAs($super);
        $this->patchJson("/api/course-modules/{$moduleId}/approval-status", ['admin_approval_status' => 'approved'])
            ->assertStatus(200);
    }

    public function test_super_admin_content_goes_live_directly(): void
    {
        $course = Course::factory()->create();
        Sanctum::actingAs($this->userWithRole('super_admin'));

        $this->postJson('/api/course-modules', ['course_id' => $course->id, 'title' => 'Live module'])
            ->assertStatus(201)
            ->assertJsonPath('data.admin_approval_status', 'approved');
    }

    public function test_admin_edit_sends_a_live_module_back_for_review(): void
    {
        $course = Course::factory()->create();
        $module = CourseModule::factory()->create(['course_id' => $course->id]);
        $module->forceFill(['admin_approval_status' => 'approved'])->save();
        Sanctum::actingAs($this->userWithRole('admin'));

        $this->patchJson("/api/course-modules/{$module->id}", ['course_id' => $course->id, 'title' => 'Edited by admin'])
            ->assertStatus(200);

        $this->assertSame('pending', $module->fresh()->admin_approval_status);
    }

    public function test_admin_created_course_needs_approval_but_super_admins_does_not(): void
    {
        $super = $this->userWithRole('super_admin');
        Sanctum::actingAs($this->userWithRole('admin'));

        $this->postJson('/api/courses', ['title' => 'By admin', 'code' => 'ADM-1', 'slug' => 'by-admin', 'price' => 100])
            ->assertStatus(201)
            ->assertJsonPath('data.admin_approval_status', 'pending');
        $this->assertDatabaseHas('notifications', ['user_id' => $super->id, 'type' => 'course_review']);

        Sanctum::actingAs($super);
        $this->postJson('/api/courses', ['title' => 'By super', 'code' => 'SUP-1', 'slug' => 'by-super', 'price' => 100])
            ->assertStatus(201)
            ->assertJsonPath('data.admin_approval_status', 'approved');
    }

    public function test_admin_cannot_approve_instructors_courses_or_mentor_applications(): void
    {
        $profile = InstructorProfile::factory()->create();
        $course = Course::factory()->create();
        Sanctum::actingAs($this->userWithRole('admin'));

        $this->patchJson("/api/instructor-profiles/{$profile->id}/approval-status", ['approval_status' => 'approved'])->assertStatus(403);
        $this->patchJson("/api/courses/{$course->id}/approval-status", ['admin_approval_status' => 'approved'])->assertStatus(403);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Cohort;
use App\Models\CohortMentorApplication;
use App\Models\InstructorDocument;
use App\Models\InstructorProfile;
use App\Models\ScholarUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CohortMentorApplicationControllerTest extends TestCase
{
    use RefreshDatabase;

    private function approvedInstructor(): User
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        InstructorProfile::factory()->create(['user_id' => $instructor->id, 'approval_status' => 'approved']);

        return $instructor;
    }

    public function test_store_requires_authentication(): void
    {
        $cohort = Cohort::factory()->create();

        $response = $this->postJson('/api/cohort-mentor-applications', [
            'cohort_id' => $cohort->id,
        ]);

        $response->assertStatus(401);
    }

    public function test_store_requires_instructor_role(): void
    {
        $student = User::factory()->create();
        ScholarUser::factory()->create(['id' => $student->id, 'role' => 'student']);
        $cohort = Cohort::factory()->create();
        Sanctum::actingAs($student);

        $response = $this->postJson('/api/cohort-mentor-applications', [
            'cohort_id' => $cohort->id,
        ]);

        $response->assertStatus(403);
    }

    public function test_store_admin_cannot_apply(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        $cohort = Cohort::factory()->create();
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/cohort-mentor-applications', [
            'cohort_id' => $cohort->id,
        ]);

        $response->assertStatus(403);
    }

    public function test_store_requires_approved_instructor_profile(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        InstructorProfile::factory()->pending()->create(['user_id' => $instructor->id]);
        $cohort = Cohort::factory()->create();
        Sanctum::actingAs($instructor);

        $response = $this->postJson('/api/cohort-mentor-applications', [
            'cohort_id' => $cohort->id,
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('cohort_mentor_applications', ['cohort_id' => $cohort->id]);
    }

    public function test_store_succeeds_for_approved_instructor_and_notifies_admins(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);

        $instructor = $this->approvedInstructor();
        $cohort = Cohort::factory()->create();
        Sanctum::actingAs($instructor);

        $response = $this->postJson('/api/cohort-mentor-applications', [
            'cohort_id' => $cohort->id,
            'message' => 'I would love to mentor this cohort.',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.status', 'pending');
        $this->assertDatabaseHas('cohort_mentor_applications', [
            'cohort_id' => $cohort->id,
            'instructor_id' => $instructor->id,
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas('notifications', ['user_id' => $admin->id, 'type' => 'mentor_application']);
    }

    public function test_store_rejects_duplicate_active_application_for_same_cohort(): void
    {
        $instructor = $this->approvedInstructor();
        $cohort = Cohort::factory()->create();
        CohortMentorApplication::factory()->create(['cohort_id' => $cohort->id, 'instructor_id' => $instructor->id, 'status' => 'pending']);
        Sanctum::actingAs($instructor);

        $response = $this->postJson('/api/cohort-mentor-applications', [
            'cohort_id' => $cohort->id,
        ]);

        $response->assertStatus(422);
    }

    public function test_store_allows_reapplying_after_rejection(): void
    {
        $instructor = $this->approvedInstructor();
        $cohort = Cohort::factory()->create();
        CohortMentorApplication::factory()->rejected()->create(['cohort_id' => $cohort->id, 'instructor_id' => $instructor->id]);
        Sanctum::actingAs($instructor);

        $response = $this->postJson('/api/cohort-mentor-applications', [
            'cohort_id' => $cohort->id,
        ]);

        $response->assertStatus(201);
    }

    public function test_index_as_instructor_only_sees_own_applications(): void
    {
        $instructor = $this->approvedInstructor();
        $ownApplication = CohortMentorApplication::factory()->create(['instructor_id' => $instructor->id]);
        CohortMentorApplication::factory()->create(); // someone else's
        Sanctum::actingAs($instructor);

        $response = $this->getJson('/api/cohort-mentor-applications');

        $response->assertStatus(200);
        $ids = collect($response->json('data.data'))->pluck('id');
        $this->assertCount(1, $ids);
        $this->assertTrue($ids->contains((string) $ownApplication->id));
    }

    public function test_index_as_admin_sees_all_and_can_filter_by_status(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        CohortMentorApplication::factory()->create(['status' => 'pending']);
        CohortMentorApplication::factory()->approved()->create();
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/cohort-mentor-applications?status=pending');

        $response->assertStatus(200);
        $statuses = collect($response->json('data.data'))->pluck('status');
        $this->assertTrue($statuses->every(fn ($s) => $s === 'pending'));
    }

    public function test_index_as_student_is_forbidden(): void
    {
        $student = User::factory()->create();
        ScholarUser::factory()->create(['id' => $student->id, 'role' => 'student']);
        Sanctum::actingAs($student);

        $response = $this->getJson('/api/cohort-mentor-applications');

        $response->assertStatus(403);
    }

    public function test_show_as_admin_succeeds(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        $application = CohortMentorApplication::factory()->create();
        Sanctum::actingAs($admin);

        $response = $this->getJson("/api/cohort-mentor-applications/{$application->id}");

        $response->assertStatus(200);
    }

    public function test_show_as_applicant_succeeds(): void
    {
        $instructor = $this->approvedInstructor();
        $application = CohortMentorApplication::factory()->create(['instructor_id' => $instructor->id]);
        Sanctum::actingAs($instructor);

        $response = $this->getJson("/api/cohort-mentor-applications/{$application->id}");

        $response->assertStatus(200);
    }

    public function test_show_as_unrelated_instructor_is_forbidden(): void
    {
        $application = CohortMentorApplication::factory()->create();
        $otherInstructor = $this->approvedInstructor();
        Sanctum::actingAs($otherInstructor);

        $response = $this->getJson("/api/cohort-mentor-applications/{$application->id}");

        $response->assertStatus(403);
    }

    public function test_set_approval_status_requires_admin(): void
    {
        $instructor = $this->approvedInstructor();
        $application = CohortMentorApplication::factory()->create();
        Sanctum::actingAs($instructor);

        $response = $this->patchJson("/api/cohort-mentor-applications/{$application->id}/approval-status", [
            'status' => 'approved',
        ]);

        $response->assertStatus(403);
    }

    public function test_set_approval_status_approve_notifies_instructor_and_writes_audit_log(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        $instructor = $this->approvedInstructor();
        $application = CohortMentorApplication::factory()->create(['instructor_id' => $instructor->id]);
        Sanctum::actingAs($admin);

        $response = $this->patchJson("/api/cohort-mentor-applications/{$application->id}/approval-status", [
            'status' => 'approved',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', 'approved');
        $this->assertDatabaseHas('admin_audit_logs', [
            'admin_id' => $admin->id,
            'action' => 'approve_mentor_application',
            'target_type' => 'cohort_mentor_application',
            'target_id' => $application->id,
        ]);
        $this->assertDatabaseHas('notifications', ['user_id' => $instructor->id, 'type' => 'mentor_application']);
    }

    public function test_set_approval_status_reject_notifies_instructor(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        $instructor = $this->approvedInstructor();
        $application = CohortMentorApplication::factory()->create(['instructor_id' => $instructor->id]);
        Sanctum::actingAs($admin);

        $response = $this->patchJson("/api/cohort-mentor-applications/{$application->id}/approval-status", [
            'status' => 'rejected',
            'rejection_reason' => 'Not enough teaching experience in this subject.',
        ]);

        $response->assertStatus(200);
        $application->refresh();
        $this->assertSame('rejected', $application->status);
        $this->assertSame('Not enough teaching experience in this subject.', $application->rejection_reason);
        $this->assertDatabaseHas('notifications', ['user_id' => $instructor->id, 'type' => 'mentor_application']);
    }

    public function test_set_approval_status_reset_clears_reviewed_fields(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        $application = CohortMentorApplication::factory()->approved()->create();
        Sanctum::actingAs($admin);

        $response = $this->patchJson("/api/cohort-mentor-applications/{$application->id}/approval-status", [
            'status' => 'pending',
        ]);

        $response->assertStatus(200);
        $application->refresh();
        $this->assertSame('pending', $application->status);
        $this->assertNull($application->reviewed_at);
        $this->assertNull($application->reviewed_by);
    }

    public function test_delete_as_applicant_withdraws_pending_application(): void
    {
        $instructor = $this->approvedInstructor();
        $application = CohortMentorApplication::factory()->create(['instructor_id' => $instructor->id, 'status' => 'pending']);
        Sanctum::actingAs($instructor);

        $response = $this->deleteJson("/api/cohort-mentor-applications/{$application->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('cohort_mentor_applications', ['id' => $application->id]);
    }

    public function test_delete_as_applicant_on_approved_application_is_forbidden(): void
    {
        $instructor = $this->approvedInstructor();
        $application = CohortMentorApplication::factory()->approved()->create(['instructor_id' => $instructor->id]);
        Sanctum::actingAs($instructor);

        $response = $this->deleteJson("/api/cohort-mentor-applications/{$application->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('cohort_mentor_applications', ['id' => $application->id]);
    }

    public function test_delete_as_admin_succeeds_regardless_of_status(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        $application = CohortMentorApplication::factory()->approved()->create();
        Sanctum::actingAs($admin);

        $response = $this->deleteJson("/api/cohort-mentor-applications/{$application->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('cohort_mentor_applications', ['id' => $application->id]);
    }

    public function test_show_gives_admin_the_applicants_qualifications(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        InstructorProfile::factory()->create([
            'user_id' => $instructor->id,
            'specialization_one' => 'Remote sensing',
            'payout_details' => 'ACC-123',
        ]);
        InstructorDocument::factory()->create(['instructor_id' => $instructor->id, 'document_type' => 'cv']);
        $application = CohortMentorApplication::factory()->create(['instructor_id' => $instructor->id]);
        Sanctum::actingAs($admin);

        $response = $this->getJson("/api/cohort-mentor-applications/{$application->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.review_context.profile.specialization_one', 'Remote sensing');
        $response->assertJsonPath('data.review_context.documents.0.document_type', 'cv');
        $response->assertJsonPath('data.review_context.missing_documents', ['national_id', 'academic_certificate']);
        $this->assertArrayNotHasKey('payout_details', $response->json('data.review_context.profile'));
    }

    public function test_show_hides_review_context_from_the_applicant(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        $application = CohortMentorApplication::factory()->create(['instructor_id' => $instructor->id]);
        Sanctum::actingAs($instructor);

        $response = $this->getJson("/api/cohort-mentor-applications/{$application->id}");

        $response->assertStatus(200);
        $this->assertNull($response->json('data.review_context'));
    }

}

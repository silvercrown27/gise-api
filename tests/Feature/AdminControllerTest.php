<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\Cohort;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\InstructorProfile;
use App\Models\Payment;
use App\Models\ScholarUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_requires_authentication(): void
    {
        $response = $this->getJson('/api/admin/dashboard');

        $response->assertStatus(401);
    }

    public function test_dashboard_is_forbidden_for_non_admin(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        Sanctum::actingAs($instructor);

        $response = $this->getJson('/api/admin/dashboard');

        $response->assertStatus(403);
    }

    public function test_dashboard_returns_expected_structure_for_admin(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'super_admin']);
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/dashboard');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'status',
            'data' => [
                'users' => ['total', 'students', 'instructors', 'admins'],
                'courses' => ['total', 'by_status', 'by_approval_status'],
                'cohorts' => ['active_count', 'average_fill_rate'],
                'enrollments' => ['total', 'last_30_days'],
                'revenue' => ['total', 'last_30_days'],
                'pending_instructors',
                'pending_courses',
                'pending_quizzes',
                'pending_mentor_applications',
                'recent_audit_logs',
            ],
        ]);
    }

    public function test_dashboard_has_trends_top_courses_upcoming_cohorts_and_extra_queues(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'super_admin']);
        Sanctum::actingAs($admin);

        $course = \App\Models\Course::factory()->create(['title' => 'Procurement', 'status' => 'published']);
        \App\Models\Enrollment::factory()->count(2)->create(['course_id' => $course->id, 'enrolled_at' => now()->subDay()]);
        \App\Models\Cohort::factory()->create(['course_id' => $course->id, 'status' => 'upcoming', 'start_date' => now()->addDays(10)->toDateString()]);

        $data = $this->getJson('/api/admin/dashboard')->assertOk()->json('data');

        $this->assertCount(30, $data['enrollments']['trend']);
        $this->assertCount(30, $data['users']['signup_trend']);
        $this->assertCount(30, $data['courses']['published_trend']);
        $this->assertGreaterThanOrEqual(1, $data['users']['last_30_days']);
        $this->assertSame(2, collect($data['enrollments']['trend'])->sum('value'));
        $this->assertSame('Procurement', $data['top_courses'][0]['title']);
        $this->assertSame(2, (int) $data['top_courses'][0]['enrollments']);
        $this->assertCount(1, $data['upcoming_cohorts']);
        $this->assertFalse($data['upcoming_cohorts'][0]['has_mentor']);
        $this->assertSame(1, $data['published_without_modules']);
        $this->assertCount(30, $data['revenue_trend']);
        foreach (['pending_change_requests', 'pending_materials', 'pending_brochure_requests', 'previous_revenue_30_days'] as $key) {
            $this->assertArrayHasKey($key, $data);
        }
    }

    public function test_dashboard_hides_revenue_and_review_queues_from_plain_admins(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        Sanctum::actingAs($admin);

        $data = $this->getJson('/api/admin/dashboard')->assertStatus(200)->json('data');

        foreach (['revenue', 'revenue_trend', 'pending_change_requests', 'published_without_modules', 'pending_instructors', 'pending_courses', 'pending_quizzes', 'pending_mentor_applications', 'pending_exams', 'pending_modules'] as $key) {
            $this->assertArrayNotHasKey($key, $data);
        }
        foreach (['users', 'courses', 'cohorts', 'enrollments', 'recent_audit_logs'] as $key) {
            $this->assertArrayHasKey($key, $data);
        }
    }

    public function test_dashboard_reports_correct_user_role_counts(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);

        $student1 = User::factory()->create();
        ScholarUser::factory()->create(['id' => $student1->id, 'role' => 'student']);
        $student2 = User::factory()->create();
        ScholarUser::factory()->create(['id' => $student2->id, 'role' => 'student']);

        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/dashboard');

        $response->assertStatus(200);
        $response->assertJsonPath('data.users.total', 4);
        $response->assertJsonPath('data.users.students', 2);
        $response->assertJsonPath('data.users.instructors', 1);
        $response->assertJsonPath('data.users.admins', 1);
    }

    public function test_dashboard_reports_correct_course_and_pending_counts(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'super_admin']);

        Course::factory()->create(['status' => 'published']);
        Course::factory()->create(['status' => 'published']);
        Course::factory()->pendingApproval()->create(['status' => 'draft']);

        $pendingInstructor = User::factory()->create();
        InstructorProfile::factory()->create(['user_id' => $pendingInstructor->id, 'approval_status' => 'pending']);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/dashboard');

        $response->assertStatus(200);
        $response->assertJsonPath('data.courses.total', 3);
        $response->assertJsonPath('data.courses.by_status.published', 2);
        $response->assertJsonPath('data.courses.by_status.draft', 1);
        $response->assertJsonPath('data.pending_courses', 1);
        $response->assertJsonPath('data.pending_instructors', 1);
    }

    public function test_dashboard_reports_correct_pending_quiz_and_mentor_application_counts(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'super_admin']);

        \App\Models\ModuleQuiz::factory()->create(['admin_approval_status' => 'pending']);
        \App\Models\ModuleQuiz::factory()->create(['admin_approval_status' => 'approved']);

        \App\Models\CohortMentorApplication::factory()->create(['status' => 'pending']);
        \App\Models\CohortMentorApplication::factory()->approved()->create();

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/dashboard');

        $response->assertStatus(200);
        $response->assertJsonPath('data.pending_quizzes', 1);
        $response->assertJsonPath('data.pending_mentor_applications', 1);
    }

    public function test_dashboard_computes_revenue_from_completed_payments_only(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'super_admin']);

        Payment::factory()->create(['status' => 'completed', 'amount' => 10000, 'paid_at' => now()]);
        Payment::factory()->create(['status' => 'completed', 'amount' => 5000, 'paid_at' => now()]);
        Payment::factory()->create(['status' => 'pending', 'amount' => 9999, 'paid_at' => now()]);
        Payment::factory()->create(['status' => 'failed', 'amount' => 9999, 'paid_at' => now()]);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/dashboard');

        $response->assertStatus(200);
        $response->assertJsonPath('data.revenue.total', 15000);
    }

    public function test_dashboard_computes_cohort_fill_rate_for_active_cohorts_only(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);

        Cohort::factory()->create(['status' => 'open', 'capacity' => 100, 'seats_taken' => 50]);
        Cohort::factory()->create(['status' => 'upcoming', 'capacity' => 100, 'seats_taken' => 30]);
        Cohort::factory()->create(['status' => 'completed', 'capacity' => 100, 'seats_taken' => 100]); // excluded

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/dashboard');

        $response->assertStatus(200);
        $response->assertJsonPath('data.cohorts.active_count', 2);
        $this->assertEquals(40.0, $response->json('data.cohorts.average_fill_rate'));
    }

    public function test_dashboard_counts_enrollments_in_last_30_days(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);

        Enrollment::factory()->create(['enrolled_at' => now()->subDays(5)]);
        Enrollment::factory()->create(['enrolled_at' => now()->subDays(60)]);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/dashboard');

        $response->assertStatus(200);
        $response->assertJsonPath('data.enrollments.total', 2);
        $response->assertJsonPath('data.enrollments.last_30_days', 1);
    }

    public function test_dashboard_returns_five_most_recent_audit_logs(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);

        AdminAuditLog::factory()->count(7)->create(['admin_id' => $admin->id]);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/dashboard');

        $response->assertStatus(200);
        $this->assertCount(5, $response->json('data.recent_audit_logs'));
    }
}

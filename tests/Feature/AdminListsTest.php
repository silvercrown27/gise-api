<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\InstructorDocument;
use App\Models\InstructorProfile;
use App\Models\ScholarUser;
use App\Models\Tool;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminListsTest extends TestCase
{
    use RefreshDatabase;

    private function loginAs(string $role): User
    {
        $user = User::factory()->create();
        ScholarUser::factory()->create(['id' => $user->id, 'role' => $role, 'status' => 'active']);
        Sanctum::actingAs($user);

        return $user;
    }

    private function member(string $role, array $user = [], array $scholar = []): ScholarUser
    {
        $u = User::factory()->create($user);

        return ScholarUser::factory()->create(['id' => $u->id, 'role' => $role] + $scholar + ['status' => 'active']);
    }

    // ── users ───────────────────────────────────────────────────────────────

    public function test_user_list_counts_filters_and_searches(): void
    {
        $this->loginAs('super_admin');
        $this->member('student', ['name' => 'Amina Student', 'email' => 'amina@example.com']);
        $this->member('student', ['name' => 'Brian Student', 'email' => 'brian@example.com'], ['status' => 'suspended']);
        $this->member('instructor', ['name' => 'Chloe Mentor']);

        $res = $this->getJson('/api/scholar-users')->assertOk();
        $this->assertSame(['total' => 4, 'student' => 2, 'instructor' => 1, 'admin' => 0, 'super_admin' => 1, 'suspended' => 1, 'student_suspended' => 1], $res->json('counts'));
        $this->assertSame(10, $res->json('data.per_page'));

        $this->assertCount(2, $this->getJson('/api/scholar-users?role=student')->json('data.data'));
        $this->assertCount(1, $this->getJson('/api/scholar-users?status=suspended')->json('data.data'));
        $this->assertSame(['Amina Student'], collect($this->getJson('/api/scholar-users?q=amina')->json('data.data'))->pluck('user.name')->all());
        $this->assertCount(0, $this->getJson('/api/scholar-users?q=' . urlencode('%'))->json('data.data'), 'a percent sign is searched literally');
    }

    public function test_student_rows_carry_their_enrollment_count(): void
    {
        $this->loginAs('admin');
        $student = $this->member('student');
        Enrollment::factory()->count(2)->create(['learner_id' => $student->id]);

        $row = collect($this->getJson('/api/scholar-users?role=student')->json('data.data'))->firstWhere('id', (string) $student->id);
        $this->assertSame(2, $row['enrollments_count']);
    }

    public function test_a_student_only_sees_themselves_and_no_counts(): void
    {
        $me = $this->loginAs('student');
        $this->member('student');

        $res = $this->getJson('/api/scholar-users')->assertOk();
        $this->assertSame([(string) $me->id], collect($res->json('data.data'))->pluck('id')->all());
        $this->assertNull($res->json('counts'));
        $this->assertArrayNotHasKey('enrollments_count', $res->json('data.data.0'));
    }

    // ── instructors ─────────────────────────────────────────────────────────

    public function test_instructor_list_searches_counts_and_shows_document_totals(): void
    {
        $this->loginAs('admin');
        $a = $this->member('instructor', ['name' => 'Zed Mentor', 'email' => 'zed@example.com']);
        $b = $this->member('instructor', ['name' => 'Yara Mentor']);
        InstructorProfile::factory()->create(['user_id' => $a->id, 'approval_status' => 'pending']);
        InstructorProfile::factory()->create(['user_id' => $b->id, 'approval_status' => 'approved']);
        InstructorDocument::factory()->count(2)->create(['instructor_id' => $a->id]);

        $res = $this->getJson('/api/instructor-profiles')->assertOk();
        $this->assertSame(['total' => 2, 'pending' => 1, 'approved' => 1, 'banned' => 0], $res->json('counts'));

        $found = $this->getJson('/api/instructor-profiles?q=zed')->json('data.data');
        $this->assertCount(1, $found);
        $this->assertSame(2, $found[0]['documents_count']);
        $this->assertCount(1, $this->getJson('/api/instructor-profiles?approval_status=approved')->json('data.data'));
    }

    // ── catalogue ───────────────────────────────────────────────────────────

    public function test_categories_can_be_filtered_by_whether_they_have_courses(): void
    {
        $this->loginAs('admin');
        $used = Category::factory()->create(['name' => 'Used', 'classification' => 'skills_professional']);
        Category::factory()->create(['name' => 'Empty', 'classification' => 'skills_professional']);
        Category::factory()->create(['name' => 'Other level', 'classification' => 'o_level']);
        Course::factory()->create(['category_id' => $used->id, 'status' => 'draft']);

        $names = fn (string $q) => collect($this->getJson('/api/categories?classification=skills_professional' . $q)->json('data.data'))->pluck('name')->all();

        $this->assertEqualsCanonicalizing(['Used', 'Empty'], $names(''));
        $this->assertSame(['Used'], $names('&has_courses=with'));
        $this->assertSame(['Empty'], $names('&has_courses=without'));
        $this->assertSame(['total' => 2, 'with_courses' => 1, 'without_courses' => 1], $this->getJson('/api/categories?classification=skills_professional')->json('counts'));

        $row = collect($this->getJson('/api/categories?classification=skills_professional')->json('data.data'))->firstWhere('name', 'Used');
        $this->assertSame(1, $row['courses_count'], 'admins see every course, drafts included');
        $this->assertSame(0, $row['available_courses_count'], 'but only live courses are "available"');
    }

    public function test_the_public_category_list_does_not_leak_admin_numbers(): void
    {
        $cat = Category::factory()->create();
        Course::factory()->create(['category_id' => $cat->id, 'status' => 'draft']);

        $res = $this->getJson('/api/categories')->assertOk();
        $this->assertNull($res->json('counts'));
        $this->assertArrayNotHasKey('courses_count', $res->json('data.data.0'));
        $this->assertCount(1, $this->getJson('/api/categories?has_courses=without')->json('data.data'), 'the admin-only filter is ignored for visitors');
    }

    public function test_tools_can_be_filtered_by_whether_courses_use_them(): void
    {
        $this->loginAs('admin');
        $used = Tool::factory()->create(['name' => 'Used tool']);
        Tool::factory()->create(['name' => 'Idle tool']);
        $course = Course::factory()->create();
        \App\Models\CourseTool::create(['course_id' => $course->id, 'tool_id' => $used->id]);

        $names = fn (string $q) => collect($this->getJson('/api/tools' . $q, ['Authorization' => 'Bearer x'])->json('data.data'))->pluck('name')->all();

        $this->assertSame(['Used tool'], $names('?has_courses=with'));
        $this->assertSame(['Idle tool'], $names('?has_courses=without'));
        $this->assertSame(['total' => 2, 'with_courses' => 1, 'without_courses' => 1], $this->getJson('/api/tools', ['Authorization' => 'Bearer x'])->json('counts'));
    }
}

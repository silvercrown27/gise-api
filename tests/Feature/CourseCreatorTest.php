<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseMentor;
use App\Models\ScholarUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CourseCreatorTest extends TestCase
{
    use RefreshDatabase;

    private function login(string $role, string $name): User
    {
        $user = User::factory()->create(['name' => $name]);
        ScholarUser::factory()->create(['id' => $user->id, 'role' => $role, 'status' => 'active']);
        Sanctum::actingAs($user);

        return $user;
    }

    private function newCourse(array $extra = [])
    {
        return $this->postJson('/api/courses', $extra + ['title' => 'Made by someone', 'code' => 'X1', 'slug' => 'made-by-someone', 'price' => 10]);
    }

    public function test_the_creator_is_recorded_separately_from_the_owner(): void
    {
        $owner = $this->login('super_admin', 'Platform Owner');
        $admin = $this->login('admin', 'Other Admin');

        $this->newCourse()->assertCreated();

        $course = Course::firstOrFail();
        $this->assertSame((string) $owner->id, (string) $course->instructor_id, 'every course is filed under the platform owner');
        $this->assertSame((string) $admin->id, (string) $course->created_by, 'but the admin who made it is remembered');
    }

    public function test_a_client_cannot_choose_or_change_the_creator(): void
    {
        $this->login('super_admin', 'Owner');
        $other = User::factory()->create();
        $me = $this->login('admin', 'Real Creator');

        $this->newCourse(['created_by' => (string) $other->id])->assertCreated();
        $course = Course::firstOrFail();
        $this->assertSame((string) $me->id, (string) $course->created_by);

        $this->patchJson("/api/courses/{$course->id}", ['title' => 'Renamed', 'code' => 'X1', 'slug' => 'made-by-someone', 'price' => 10, 'created_by' => (string) $other->id])->assertOk();
        $this->assertSame((string) $me->id, (string) $course->fresh()->created_by);
    }

    public function test_the_admin_course_page_gets_the_creator_and_the_mentor_not_just_the_owner(): void
    {
        $owner = $this->login('super_admin', 'Platform Owner');
        $admin = User::factory()->create(['name' => 'Course Author']);
        $course = Course::factory()->create(['instructor_id' => $owner->id, 'created_by' => $admin->id]);
        CourseMentor::create(['course_id' => $course->id, 'name' => 'Dr. Real Mentor', 'bio' => 'x', 'assigned_at' => now()]);

        $data = $this->getJson("/api/courses/{$course->id}/curriculum")->assertOk()->json('data.course') ?? $this->getJson("/api/courses/{$course->id}/curriculum")->json('course');

        $this->assertSame('Course Author', $data['creator']['name']);
        $this->assertSame('Dr. Real Mentor', $data['mentor']['name']);
        $this->assertSame('Platform Owner', $data['instructor']['name']);
    }

    public function test_older_courses_without_a_recorded_creator_still_load(): void
    {
        $owner = $this->login('super_admin', 'Platform Owner');
        $course = Course::factory()->create(['instructor_id' => $owner->id, 'created_by' => null]);

        $json = $this->getJson("/api/courses/{$course->id}/curriculum")->assertOk()->json();
        $found = $json['data']['course'] ?? $json['course'];
        $this->assertNull($found['creator']);
    }
}

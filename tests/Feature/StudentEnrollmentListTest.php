<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\ScholarUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StudentEnrollmentListTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_filters_searches_and_counts_their_enrollments(): void
    {
        $user = User::factory()->create();
        ScholarUser::factory()->create(['id' => $user->id, 'role' => 'student', 'status' => 'active']);
        Sanctum::actingAs($user);
        $other = User::factory()->create();
        ScholarUser::factory()->create(['id' => $other->id, 'role' => 'student', 'status' => 'active']);

        foreach ([['Procurement', 'active'], ['Mapping', 'completed'], ['Cold Chain', 'completed']] as [$title, $state]) {
            Enrollment::factory()->create(['learner_id' => $user->id, 'course_id' => Course::factory()->create(['title' => $title])->id, 'enrollment_status' => $state]);
        }
        Enrollment::factory()->create(['learner_id' => $other->id, 'enrollment_status' => 'active']);

        $res = $this->getJson('/api/enrollments?status=completed')->assertOk();
        $this->assertSame(3, $res->json('counts.total'));
        $this->assertSame(2, $res->json('counts.completed'));
        $this->assertCount(2, $res->json('data.data'));
        $this->assertCount(1, $this->getJson('/api/enrollments?q=procure')->json('data.data'));
    }
}

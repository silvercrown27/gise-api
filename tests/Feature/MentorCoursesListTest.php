<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\ScholarUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MentorCoursesListTest extends TestCase
{
    use RefreshDatabase;

    public function test_my_courses_search_filter_and_counts(): void
    {
        $user = User::factory()->create();
        ScholarUser::factory()->create(['id' => $user->id, 'role' => 'super_admin', 'status' => 'active']);
        Sanctum::actingAs($user);
        Course::factory()->create(['title' => 'Procurement Basics', 'status' => 'published']);
        Course::factory()->create(['title' => 'Mapping Basics', 'status' => 'draft']);
        Course::factory()->create(['title' => 'Cold Chain', 'status' => 'draft']);

        $res = $this->getJson('/api/courses/mine?status=draft')->assertOk();
        $this->assertSame(['total' => 3, 'published' => 1, 'draft' => 2, 'archived' => 0], $res->json('counts'));
        $this->assertCount(2, $res->json('data.data'));

        $this->assertCount(1, $this->getJson('/api/courses/mine?q=procurement')->json('data.data'));
    }
}

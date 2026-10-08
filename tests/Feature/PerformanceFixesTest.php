<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\CourseModule;
use App\Models\ScholarUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PerformanceFixesTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_listing_is_cached_and_flagged_for_cloudflare(): void
    {
        Category::factory()->create(['name' => 'Alpha']);

        $first = $this->getJson('/api/categories');
        $first->assertOk()->assertHeader('X-Cache', 'MISS');
        $this->assertStringContainsString('s-maxage=300', $first->headers->get('Cache-Control'));
        $this->assertStringContainsString('public', $first->headers->get('Cache-Control'));

        $second = $this->getJson('/api/categories');
        $second->assertOk()->assertHeader('X-Cache', 'HIT');
        $this->assertSame($first->json(), $second->json());
    }

    public function test_cache_is_dropped_when_the_data_changes(): void
    {
        Category::factory()->create(['name' => 'Alpha']);
        $this->getJson('/api/categories')->assertHeader('X-Cache', 'MISS');
        $this->getJson('/api/categories')->assertHeader('X-Cache', 'HIT');

        Category::factory()->create(['name' => 'Beta']);

        $after = $this->getJson('/api/categories');
        $after->assertHeader('X-Cache', 'MISS');
        $this->assertStringContainsString('Beta', $after->getContent());
    }

    public function test_query_string_is_part_of_the_cache_key(): void
    {
        Course::factory()->create(['status' => 'published', 'admin_approval_status' => 'approved', 'title' => 'Algebra']);
        Course::factory()->create(['status' => 'published', 'admin_approval_status' => 'approved', 'title' => 'Biology']);

        $a = $this->getJson('/api/courses?q=Algebra');
        $b = $this->getJson('/api/courses?q=Biology');
        $this->assertStringContainsString('Algebra', $a->getContent());
        $this->assertStringNotContainsString('Algebra', $b->getContent());
        // parameter order must not matter
        $this->getJson('/api/courses?per_page=5&sort=title')->assertHeader('X-Cache', 'MISS');
        $this->getJson('/api/courses?sort=title&per_page=5')->assertHeader('X-Cache', 'HIT');
    }

    public function test_requests_with_credentials_are_never_cached_or_made_public(): void
    {
        $course = Course::factory()->create(['status' => 'published', 'admin_approval_status' => 'approved']);
        Sanctum::actingAs(User::factory()->create());

        $res = $this->getJson("/api/courses/{$course->id}", ['Authorization' => 'Bearer x']);
        $res->assertOk();
        $this->assertNull($res->headers->get('X-Cache'));
        $this->assertStringNotContainsString('public', (string) $res->headers->get('Cache-Control'));
    }

    public function test_errors_are_not_cached(): void
    {
        $this->getJson('/api/cohorts/next')->assertStatus(404);
        $this->getJson('/api/cohorts/next')->assertStatus(404)->assertHeaderMissing('X-Cache');
    }

    public function test_scholar_user_is_looked_up_once_per_request(): void
    {
        $user = User::factory()->create();
        ScholarUser::factory()->create(['id' => $user->id, 'role' => 'instructor']);
        Sanctum::actingAs($user);

        $request = request();
        $request->setUserResolver(fn () => $user);
        DB::enableQueryLog();
        $first = $request->scholarUser();
        $second = $request->scholarUser();

        $this->assertSame($first, $second);
        $this->assertSame('instructor', $first->role);
        $this->assertCount(1, DB::getQueryLog());
    }

    public function test_scholar_user_is_null_for_guests(): void
    {
        $request = request();
        $request->setUserResolver(fn () => null);
        $this->assertNull($request->scholarUser());
    }

    public function test_invalid_lesson_does_not_leave_an_orphan_file(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'super_admin']);
        Sanctum::actingAs($admin);
        $module = CourseModule::factory()->create();

        $this->post('/api/course-lessons', [
            'module_id' => (string) $module->id,
            'content_type' => 'pdf',                    // title is missing -> invalid
            'content_file' => UploadedFile::fake()->create('l.pdf', 50, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertStatus(422);

        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_valid_lesson_stores_its_file_after_validation(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'super_admin']);
        Sanctum::actingAs($admin);
        $module = CourseModule::factory()->create();

        $this->post('/api/course-lessons', [
            'module_id' => (string) $module->id, 'title' => 'Intro', 'content_type' => 'pdf',
            'content_file' => UploadedFile::fake()->create('l.pdf', 50, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertStatus(201);

        $lesson = CourseLesson::firstOrFail();
        $this->assertStringContainsString('/storage/course-lessons/', $lesson->content_url_or_body);
        $this->assertCount(1, Storage::disk('public')->allFiles());
    }

    public function test_oversized_lesson_file_is_refused_without_being_stored(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'super_admin']);
        Sanctum::actingAs($admin);
        $module = CourseModule::factory()->create();

        $this->post('/api/course-lessons', [
            'module_id' => (string) $module->id, 'title' => 'Intro', 'content_type' => 'pdf',
            'content_file' => UploadedFile::fake()->create('big.pdf', 102401, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonValidationErrors('content_file');

        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_material_list_page_size_is_capped(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'super_admin']);
        Sanctum::actingAs($admin);

        $this->getJson('/api/course-materials?per_page=500')->assertOk()->assertJsonPath('data.per_page', 100);
    }
}

<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\Category;
use App\Models\Cohort;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\ScholarUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminCourseListTest extends TestCase
{
    use RefreshDatabase;

    private function loginAs(string $role): User
    {
        $user = User::factory()->create();
        ScholarUser::factory()->create(['id' => $user->id, 'role' => $role]);
        Sanctum::actingAs($user);

        return $user;
    }

    private function course(array $attrs = []): Course
    {
        // The factory always marks a course approved, so apply the requested approval afterwards.
        $approval = $attrs['admin_approval_status'] ?? 'approved';
        unset($attrs['admin_approval_status']);
        $course = Course::factory()->create($attrs + ['status' => 'published']);

        if ($approval !== 'approved') {
            $course->forceFill(['admin_approval_status' => $approval])->save();
        }

        return $course;
    }

    private function titles(string $query = ''): array
    {
        return collect($this->getJson('/api/courses/admin' . $query)->assertOk()->json('data.data'))->pluck('title')->all();
    }

    public function test_only_admins_can_use_the_admin_list(): void
    {
        $this->getJson('/api/courses/admin')->assertStatus(401);

        $this->loginAs('student');
        $this->getJson('/api/courses/admin')->assertStatus(403);
        $this->postJson('/api/courses/bulk', ['action' => 'publish', 'ids' => []])->assertStatus(403);
    }

    public function test_search_covers_title_code_slug_tagline_and_category(): void
    {
        $this->loginAs('admin');
        $cat = Category::factory()->create(['name' => 'Logistics Studio']);
        $this->course(['title' => 'Alpha Course', 'code' => 'P900', 'slug' => 'alpha', 'tagline' => 'about pallets', 'category_id' => $cat->id]);
        $this->course(['title' => 'Beta Course', 'code' => 'P901', 'slug' => 'beta-slug', 'tagline' => 'about ships']);

        $this->assertSame(['Alpha Course'], $this->titles('?q=P900'));
        $this->assertSame(['Beta Course'], $this->titles('?q=beta-slug'));
        $this->assertSame(['Beta Course'], $this->titles('?q=ships'));
        $this->assertSame(['Alpha Course'], $this->titles('?q=Logistics'));
        $this->assertCount(2, $this->titles('?q=course'));
        $this->assertSame([], $this->titles('?q=zzzz'));
    }

    public function test_search_treats_percent_and_underscore_literally(): void
    {
        $this->loginAs('admin');
        $this->course(['title' => 'Plain title']);
        $this->course(['title' => '100% Safe']);

        $this->assertSame(['100% Safe'], $this->titles('?q=' . urlencode('100%')));
        $this->assertSame([], $this->titles('?q=' . urlencode('_')));
    }

    public function test_views_filter_and_counts_add_up(): void
    {
        $this->loginAs('super_admin');
        $this->course(['title' => 'Live old', 'published_at' => now()->subDays(90)]);
        $this->course(['title' => 'Live new', 'published_at' => now()->subDays(3)]);
        $this->course(['title' => 'Waiting', 'status' => 'draft', 'admin_approval_status' => 'pending']);
        $this->course(['title' => 'Refused', 'status' => 'draft', 'admin_approval_status' => 'rejected']);
        $this->course(['title' => 'Shelved', 'status' => 'archived']);
        $gone = $this->course(['title' => 'Gone']);
        $gone->delete();

        $this->assertSame(['Live new'], $this->titles('?view=recent'));
        $this->assertSame(['Waiting'], $this->titles('?view=pending'));
        $this->assertSame(['Refused'], $this->titles('?view=rejected'));
        $this->assertSame(['Shelved'], $this->titles('?view=archived'));
        $this->assertSame(['Gone'], $this->titles('?view=removed'));
        $this->assertEqualsCanonicalizing(['Live old', 'Live new'], $this->titles('?view=published'));
        $this->assertNotContains('Gone', $this->titles());

        $counts = $this->getJson('/api/courses/admin')->json('counts');
        $this->assertSame(['total' => 5, 'pending' => 1, 'rejected' => 1, 'published' => 2, 'draft' => 2, 'archived' => 1, 'recent' => 1, 'removed' => 1], $counts);
    }

    public function test_sorting_and_page_size(): void
    {
        $this->loginAs('admin');
        foreach (['Bravo' => 300, 'Alpha' => 100, 'Charlie' => 200] as $title => $price) {
            $this->course(['title' => $title, 'price' => $price]);
        }

        $this->assertSame(['Alpha', 'Bravo', 'Charlie'], $this->titles('?sort=title'));
        $this->assertSame(['Alpha', 'Charlie', 'Bravo'], $this->titles('?sort=price_asc'));
        $this->assertSame(['Bravo', 'Charlie', 'Alpha'], $this->titles('?sort=price_desc'));
        $this->getJson('/api/courses/admin?per_page=5000')->assertJsonPath('data.per_page', 100);
        $this->getJson('/api/courses/admin?per_page=1')->assertJsonPath('data.per_page', 10);
    }

    public function test_needs_attention_filters_and_row_counts(): void
    {
        $this->loginAs('admin');
        $cat = Category::factory()->create();
        $full = $this->course(['title' => 'Complete', 'category_id' => $cat->id, 'thumbnail_url' => '/storage/course-thumbnails/a.jpg', 'price' => 50]);
        $this->course(['title' => 'Bare', 'category_id' => null, 'thumbnail_url' => null, 'price' => 0]);
        Cohort::factory()->create(['course_id' => $full->id, 'start_date' => now()->addDays(10)->toDateString(), 'status' => 'upcoming']);

        $this->assertSame(['Bare'], $this->titles('?issue=no_image'));
        $this->assertSame(['Bare'], $this->titles('?issue=no_category'));
        $this->assertSame(['Bare'], $this->titles('?issue=no_cohort'));
        $this->assertSame(['Bare'], $this->titles('?issue=no_price'));

        $row = collect($this->getJson('/api/courses/admin?q=Complete')->json('data.data'))->first();
        $this->assertSame(1, $row['upcoming_cohorts_count']);
        $this->assertSame(0, $row['enrollments_count']);
        $this->assertArrayNotHasKey('full_description', $row);
    }

    public function test_removing_a_course_with_learners_needs_force_and_can_be_restored(): void
    {
        $admin = $this->loginAs('admin');
        $course = $this->course(['title' => 'Busy']);
        Enrollment::factory()->create(['course_id' => $course->id, 'enrollment_status' => 'active']);

        $this->deleteJson("/api/courses/{$course->id}")->assertStatus(409)->assertJsonPath('enrollments_count', 1);
        $this->assertNotNull(Course::find($course->id));

        $this->deleteJson("/api/courses/{$course->id}?force=1")->assertOk();
        $this->assertNull(Course::find($course->id));
        $this->assertSame(['Busy'], $this->titles('?view=removed'));
        $this->assertDatabaseHas('admin_audit_logs', ['admin_id' => $admin->id, 'action' => 'remove_course', 'target_id' => $course->id]);

        $this->postJson("/api/courses/{$course->id}/restore")->assertOk();
        $this->assertNotNull(Course::find($course->id));
        $this->postJson("/api/courses/{$course->id}/restore")->assertStatus(404);
    }

    public function test_a_course_without_learners_is_removed_straight_away(): void
    {
        $this->loginAs('admin');
        $course = $this->course();
        $this->deleteJson("/api/courses/{$course->id}")->assertOk();
        $this->assertSoftDeleted('courses', ['id' => $course->id]);
    }

    public function test_bulk_status_changes(): void
    {
        $this->loginAs('admin');
        $a = $this->course(['status' => 'draft', 'published_at' => null]);
        $b = $this->course(['status' => 'published']);

        $this->postJson('/api/courses/bulk', ['action' => 'publish', 'ids' => [(string) $a->id]])->assertOk()->assertJsonPath('data.done.0', (string) $a->id);
        $this->assertSame('published', $a->fresh()->status);
        $this->assertNotNull($a->fresh()->published_at);

        $this->postJson('/api/courses/bulk', ['action' => 'archive', 'ids' => [(string) $a->id, (string) $b->id]])->assertOk();
        $this->assertSame(['archived', 'archived'], [$a->fresh()->status, $b->fresh()->status]);

        $this->postJson('/api/courses/bulk', ['action' => 'draft', 'ids' => [(string) $a->id]])->assertOk();
        $this->assertSame('draft', $a->fresh()->status);
    }

    public function test_bulk_approve_is_super_admin_only_and_publishes_drafts(): void
    {
        $course = $this->course(['status' => 'draft', 'admin_approval_status' => 'pending']);

        $this->loginAs('admin');
        $this->postJson('/api/courses/bulk', ['action' => 'approve', 'ids' => [(string) $course->id]])->assertStatus(403);
        $this->assertSame('pending', $course->fresh()->admin_approval_status);

        $this->loginAs('super_admin');
        $this->postJson('/api/courses/bulk', ['action' => 'approve', 'ids' => [(string) $course->id]])->assertOk();
        $this->assertSame(['approved', 'published'], [$course->fresh()->admin_approval_status, $course->fresh()->status]);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'approve_course', 'target_id' => $course->id]);
    }

    public function test_bulk_remove_skips_courses_with_learners_and_reports_them(): void
    {
        $this->loginAs('admin');
        $free = $this->course(['title' => 'Quiet']);
        $busy = $this->course(['title' => 'Busy']);
        Enrollment::factory()->create(['course_id' => $busy->id, 'enrollment_status' => 'active']);

        $res = $this->postJson('/api/courses/bulk', ['action' => 'remove', 'ids' => [(string) $free->id, (string) $busy->id]])->assertOk();

        $this->assertSame([(string) $free->id], $res->json('data.done'));
        $this->assertSame((string) $busy->id, $res->json('data.skipped.0.id'));
        $this->assertStringContainsString('enrolled', $res->json('data.skipped.0.reason'));
        $this->assertNull(Course::find($free->id));
        $this->assertNotNull(Course::find($busy->id));

        $this->postJson('/api/courses/bulk', ['action' => 'remove', 'ids' => [(string) $busy->id], 'force' => true])->assertOk()->assertJsonPath('data.done.0', (string) $busy->id);
        $this->postJson('/api/courses/bulk', ['action' => 'restore', 'ids' => [(string) $free->id, (string) $busy->id]])->assertOk();
        $this->assertNotNull(Course::find($free->id));
    }

    public function test_bulk_validates_input(): void
    {
        $this->loginAs('admin');
        $this->postJson('/api/courses/bulk', ['action' => 'explode', 'ids' => ['x']])->assertStatus(422);
        $this->postJson('/api/courses/bulk', ['action' => 'publish', 'ids' => []])->assertStatus(422);
        $res = $this->postJson('/api/courses/bulk', ['action' => 'publish', 'ids' => ['0c9277a9-6439-4169-a2c2-79054f907837']])->assertOk();
        $this->assertSame([], $res->json('data.done'));
        $this->assertCount(1, $res->json('data.skipped'));
    }

    public function test_replacing_the_image_stores_the_new_one_and_deletes_the_old(): void
    {
        Storage::fake('public');
        $this->loginAs('admin');
        Storage::disk('public')->put('course-thumbnails/old.jpg', 'old');
        $course = $this->course(['thumbnail_url' => '/storage/course-thumbnails/old.jpg']);

        $res = $this->post("/api/courses/{$course->id}", $this->fields($course) + [
            '_method' => 'PATCH',
            'thumbnail' => UploadedFile::fake()->image('new.jpg', 1080, 720),
        ], ['Accept' => 'application/json'])->assertOk();

        $new = $res->json('data.thumbnail_url');
        $this->assertNotSame('/storage/course-thumbnails/old.jpg', $new);
        $this->assertFalse(Storage::disk('public')->exists('course-thumbnails/old.jpg'), 'the old file is cleaned up');
        $this->assertCount(1, Storage::disk('public')->files('course-thumbnails'));
    }

    public function test_image_can_be_removed(): void
    {
        Storage::fake('public');
        $this->loginAs('admin');
        Storage::disk('public')->put('course-thumbnails/old.jpg', 'old');
        $course = $this->course(['thumbnail_url' => '/storage/course-thumbnails/old.jpg']);

        $this->post("/api/courses/{$course->id}", $this->fields($course) + ['_method' => 'PATCH', 'remove_thumbnail' => '1'], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('data.thumbnail_url', null);
        $this->assertSame([], Storage::disk('public')->files('course-thumbnails'));
    }

    public function test_only_real_pictures_are_accepted_as_the_image(): void
    {
        Storage::fake('public');
        $this->loginAs('admin');
        $course = $this->course(['thumbnail_url' => '/storage/course-thumbnails/keep.jpg']);
        Storage::disk('public')->put('course-thumbnails/keep.jpg', 'keep');

        foreach ([UploadedFile::fake()->create('shell.php', 5, 'application/x-php'), UploadedFile::fake()->create('doc.pdf', 5, 'application/pdf'), UploadedFile::fake()->create('big.jpg', 9000, 'image/jpeg')] as $file) {
            $this->post("/api/courses/{$course->id}", $this->fields($course) + ['_method' => 'PATCH', 'thumbnail' => $file], ['Accept' => 'application/json'])
                ->assertStatus(422)->assertJsonValidationErrors('thumbnail');
        }

        $this->assertSame('/storage/course-thumbnails/keep.jpg', $course->fresh()->thumbnail_url);
        $this->assertTrue(Storage::disk('public')->exists('course-thumbnails/keep.jpg'));
    }

    public function test_an_outside_image_url_is_never_deleted_from_disk(): void
    {
        Storage::fake('public');
        $this->loginAs('admin');
        $course = $this->course(['thumbnail_url' => 'https://cdn.example.com/pic.jpg']);

        $this->post("/api/courses/{$course->id}", $this->fields($course) + ['_method' => 'PATCH', 'remove_thumbnail' => '1'], ['Accept' => 'application/json'])->assertOk();
        $this->assertNull($course->fresh()->thumbnail_url);
    }

    public function test_editing_details_works_for_admins_and_students_cannot(): void
    {
        $this->loginAs('admin');
        $course = $this->course(['title' => 'Before']);

        $this->patchJson("/api/courses/{$course->id}", $this->fields($course, ['title' => 'After', 'price' => 99]))->assertOk();
        $this->assertSame(['After', 99], [$course->fresh()->title, $course->fresh()->price]);

        $this->loginAs('student');
        $this->patchJson("/api/courses/{$course->id}", $this->fields($course, ['title' => 'Hacked']))->assertStatus(403);
    }

    public function test_the_public_listing_forgets_a_course_when_it_is_removed_and_remembers_it_when_restored(): void
    {
        $this->loginAs('admin');
        $course = $this->course(['title' => 'Visible']);

        $this->assertStringContainsString('Visible', $this->getJson('/api/courses')->getContent());
        $this->deleteJson("/api/courses/{$course->id}")->assertOk();
        $this->assertStringNotContainsString('Visible', $this->getJson('/api/courses')->getContent());
        $this->postJson("/api/courses/{$course->id}/restore")->assertOk();
        $this->assertStringContainsString('Visible', $this->getJson('/api/courses')->getContent());
    }

    private function fields(Course $course, array $override = []): array
    {
        return array_merge(['title' => $course->title, 'code' => $course->code, 'slug' => $course->slug, 'price' => $course->price, 'status' => $course->status], $override);
    }
}

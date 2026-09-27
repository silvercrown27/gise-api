<?php

namespace Tests\Feature;

use App\Models\Cohort;
use App\Models\CohortMentorApplication;
use App\Models\Course;
use App\Models\CourseMaterial;
use App\Models\CourseModule;
use App\Models\Enrollment;
use App\Models\ScholarUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CourseMaterialControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function mentorOf(Course $course): User
    {
        $mentor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $mentor->id, 'role' => 'instructor']);
        $cohort = Cohort::factory()->create(['course_id' => $course->id]);
        CohortMentorApplication::factory()->create(['cohort_id' => $cohort->id, 'instructor_id' => $mentor->id, 'status' => 'approved']);

        return $mentor;
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'super_admin']);

        return $admin;
    }

    private function upload(array $fields)
    {
        return $this->post('/api/course-materials', $fields, ['Accept' => 'application/json']);
    }

    public function test_mentor_upload_is_pending_and_admins_are_notified(): void
    {
        $course = Course::factory()->create();
        $admin = $this->admin();
        Sanctum::actingAs($this->mentorOf($course));

        $this->upload([
            'course_id' => $course->id,
            'type' => 'course_content',
            'file' => UploadedFile::fake()->create('content.pdf', 500, 'application/pdf'),
        ])->assertStatus(201)->assertJsonPath('data.status', 'pending')->assertJsonPath('data.title', 'Course content');

        $this->assertDatabaseHas('notifications', ['user_id' => $admin->id, 'type' => 'course_material', 'link' => '/admin/materials']);
    }

    public function test_instructor_who_does_not_mentor_the_course_cannot_upload(): void
    {
        $course = Course::factory()->create();
        $stranger = User::factory()->create();
        ScholarUser::factory()->create(['id' => $stranger->id, 'role' => 'instructor']);
        Sanctum::actingAs($stranger);

        $this->upload([
            'course_id' => $course->id,
            'type' => 'brochure',
            'file' => UploadedFile::fake()->create('b.pdf', 100, 'application/pdf'),
        ])->assertStatus(403);
    }

    public function test_file_types_are_enforced_per_material(): void
    {
        $course = Course::factory()->create();
        $module = CourseModule::factory()->create(['course_id' => $course->id]);
        Sanctum::actingAs($this->mentorOf($course));

        $this->upload([
            'course_id' => $course->id, 'type' => 'brochure',
            'file' => UploadedFile::fake()->create('deck.pptx', 100, 'application/vnd.openxmlformats-officedocument.presentationml.presentation'),
        ])->assertStatus(422)->assertJsonValidationErrors('file');

        foreach (['deck.pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation', 'deck.ppt' => 'application/vnd.ms-powerpoint', 'deck.pdf' => 'application/pdf'] as $name => $mime) {
            $this->upload([
                'course_id' => $course->id, 'type' => 'module_slides', 'module_id' => (string) $module->id,
                'file' => UploadedFile::fake()->create($name, 100, $mime),
            ])->assertStatus(201, $name);
        }

        $this->assertSame(3, CourseMaterial::where('module_id', $module->id)->count());
    }

    public function test_slides_need_a_module_of_the_same_course(): void
    {
        $course = Course::factory()->create();
        $otherModule = CourseModule::factory()->create();
        Sanctum::actingAs($this->mentorOf($course));

        $this->upload([
            'course_id' => $course->id, 'type' => 'module_slides',
            'file' => UploadedFile::fake()->create('deck.pdf', 100, 'application/pdf'),
        ])->assertStatus(422)->assertJsonValidationErrors('module_id');

        $this->upload([
            'course_id' => $course->id, 'type' => 'module_slides', 'module_id' => (string) $otherModule->id,
            'file' => UploadedFile::fake()->create('deck.pdf', 100, 'application/pdf'),
        ])->assertStatus(422);
    }

    public function test_approving_a_brochure_makes_it_the_courses_brochure(): void
    {
        $course = Course::factory()->create(['brochure_url' => null]);
        $mentor = $this->mentorOf($course);
        $material = CourseMaterial::factory()->create([
            'course_id' => $course->id, 'type' => 'brochure', 'uploaded_by' => $mentor->id, 'file_url' => '/storage/course-materials/new.pdf',
        ]);
        Sanctum::actingAs($this->admin());

        $this->patchJson("/api/course-materials/{$material->id}/status", ['status' => 'rejected'])->assertStatus(422);
        $this->patchJson("/api/course-materials/{$material->id}/status", ['status' => 'approved'])->assertStatus(200);

        $this->assertSame('/storage/course-materials/new.pdf', $course->fresh()->brochure_url);
        $this->assertDatabaseHas('notifications', ['user_id' => $mentor->id, 'type' => 'course_material']);

        // Removing the live brochure clears it.
        $this->deleteJson("/api/course-materials/{$material->id}")->assertStatus(200);
        $this->assertNull($course->fresh()->brochure_url);
    }

    public function test_admin_upload_is_approved_immediately(): void
    {
        $course = Course::factory()->create();
        Sanctum::actingAs($this->admin());

        $this->upload([
            'course_id' => $course->id, 'type' => 'brochure',
            'file' => UploadedFile::fake()->create('b.pdf', 100, 'application/pdf'),
        ])->assertStatus(201)->assertJsonPath('data.status', 'approved');

        $this->assertNotNull($course->fresh()->brochure_url);
    }

    public function test_mentor_can_withdraw_pending_but_not_approved_uploads(): void
    {
        $course = Course::factory()->create();
        $mentor = $this->mentorOf($course);
        $pending = CourseMaterial::factory()->create(['course_id' => $course->id, 'uploaded_by' => $mentor->id]);
        $approved = CourseMaterial::factory()->create(['course_id' => $course->id, 'uploaded_by' => $mentor->id, 'status' => 'approved']);
        Sanctum::actingAs($mentor);

        $this->deleteJson("/api/course-materials/{$pending->id}")->assertStatus(200);
        $this->deleteJson("/api/course-materials/{$approved->id}")->assertStatus(403);
    }

    public function test_enrolled_learner_sees_only_approved_materials_of_their_course(): void
    {
        $course = Course::factory()->create();
        $approved = CourseMaterial::factory()->create(['course_id' => $course->id, 'status' => 'approved']);
        CourseMaterial::factory()->create(['course_id' => $course->id, 'status' => 'pending']);
        CourseMaterial::factory()->create(['status' => 'approved']);
        $learner = User::factory()->create();
        ScholarUser::factory()->create(['id' => $learner->id, 'role' => 'student']);
        Enrollment::factory()->create(['learner_id' => $learner->id, 'course_id' => $course->id]);
        Sanctum::actingAs($learner);

        $ids = collect($this->getJson('/api/course-materials')->json('data.data'))->pluck('id')->all();

        $this->assertSame([(string) $approved->id], $ids);
    }
}

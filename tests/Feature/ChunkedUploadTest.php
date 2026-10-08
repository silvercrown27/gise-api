<?php

namespace Tests\Feature;

use App\Jobs\NotifyStaff;
use App\Models\Cohort;
use App\Models\CohortMentorApplication;
use App\Models\Course;
use App\Models\CourseMaterial;
use App\Models\CourseModule;
use App\Models\InstructorDocument;
use App\Models\ScholarUser;
use App\Models\User;
use App\Services\ChunkedUploads;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChunkedUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('local');
    }

    private function pdfBytes(int $size = 3000): string
    {
        return "%PDF-1.4\n" . str_repeat('x', $size) . "\n%%EOF";
    }

    private function mentorOf(Course $course): User
    {
        $mentor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $mentor->id, 'role' => 'instructor']);
        $cohort = Cohort::factory()->create(['course_id' => $course->id]);
        CohortMentorApplication::factory()->create(['cohort_id' => $cohort->id, 'instructor_id' => $mentor->id, 'status' => 'approved']);

        return $mentor;
    }

    /** Send $bytes as chunks of $size; returns the fields to attach to the create request. */
    private function sendChunks(string $bytes, int $size = 1000, string $name = 'content.pdf', ?string $uploadId = null): array
    {
        $uploadId ??= (string) Str::uuid();
        $pieces = str_split($bytes, $size);

        foreach ($pieces as $i => $piece) {
            $this->post('/api/uploads/chunks', [
                'upload_id' => $uploadId,
                'index' => $i,
                'total' => count($pieces),
                'chunk' => UploadedFile::fake()->createWithContent("part{$i}", $piece),
            ], ['Accept' => 'application/json'])
                ->assertOk()->assertJsonPath('received', $i + 1);
        }

        return [
            'upload_id' => $uploadId,
            'upload_total' => count($pieces),
            'upload_name' => $name,
            'upload_size' => strlen($bytes),
        ];
    }

    public function test_material_can_be_uploaded_in_chunks_and_chunks_are_cleaned_up(): void
    {
        Queue::fake();
        $course = Course::factory()->create();
        $mentor = $this->mentorOf($course);
        Sanctum::actingAs($mentor);

        $bytes = $this->pdfBytes(3500);
        $fields = $this->sendChunks($bytes);
        $this->assertSame(4, $fields['upload_total']);

        $this->post('/api/course-materials', ['course_id' => $course->id, 'type' => 'course_content'] + $fields, ['Accept' => 'application/json'])
            ->assertStatus(201)
            ->assertJsonPath('data.file_size', strlen($bytes))
            ->assertJsonPath('data.file_type', 'pdf');

        $material = CourseMaterial::firstOrFail();
        $stored = Storage::disk('public')->get(str_replace('/storage/', '', parse_url($material->file_url, PHP_URL_PATH)));
        $this->assertSame($bytes, $stored, 'the stitched file must be byte-identical');

        $this->assertSame([], Storage::disk('local')->allFiles('chunks'), 'chunks are removed after the request');
        Queue::assertPushed(NotifyStaff::class);
    }

    public function test_notifications_for_an_upload_go_through_the_queue(): void
    {
        Queue::fake();
        $course = Course::factory()->create();
        Sanctum::actingAs($this->mentorOf($course));

        $this->post('/api/course-materials', [
            'course_id' => $course->id, 'type' => 'brochure',
            'file' => UploadedFile::fake()->create('b.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertStatus(201);

        Queue::assertPushed(NotifyStaff::class, fn ($job) => true);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_the_normal_validation_still_applies_to_a_chunked_file(): void
    {
        $course = Course::factory()->create();
        Sanctum::actingAs($this->mentorOf($course));

        // Named .pdf but the content is not a PDF.
        $fields = $this->sendChunks(str_repeat('MZ not a pdf ', 300), 1500, 'virus.pdf');

        $this->post('/api/course-materials', ['course_id' => $course->id, 'type' => 'course_content'] + $fields, ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonValidationErrors('file')
            ->assertJsonPath('errors.file.0', 'Upload a PDF file.');   // the real rule fired, not "required"

        $this->assertSame(0, CourseMaterial::count());
    }

    public function test_instructor_document_accepts_a_chunked_file(): void
    {
        $user = User::factory()->create();
        ScholarUser::factory()->create(['id' => $user->id, 'role' => 'instructor']);
        Sanctum::actingAs($user);

        $bytes = $this->pdfBytes(2500);
        $fields = $this->sendChunks($bytes, 1024, 'cv.pdf');

        $this->post('/api/instructor-documents', ['title' => 'My CV', 'document_type' => 'other'] + $fields, ['Accept' => 'application/json'])
            ->assertStatus(201)->assertJsonPath('data.size_bytes', strlen($bytes));

        $this->assertSame(1, InstructorDocument::count());
    }

    public function test_a_missing_chunk_is_rejected(): void
    {
        $course = Course::factory()->create();
        Sanctum::actingAs($this->mentorOf($course));
        $bytes = $this->pdfBytes(3500);
        $fields = $this->sendChunks($bytes);
        Storage::disk('local')->delete('chunks/' . auth()->id() . '/' . $fields['upload_id'] . '/00002.part');

        $this->post('/api/course-materials', ['course_id' => $course->id, 'type' => 'course_content'] + $fields, ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'incomplete'));
    }

    public function test_a_size_mismatch_is_rejected(): void
    {
        $course = Course::factory()->create();
        Sanctum::actingAs($this->mentorOf($course));
        $fields = $this->sendChunks($this->pdfBytes());
        $fields['upload_size']++;

        $this->post('/api/course-materials', ['course_id' => $course->id, 'type' => 'course_content'] + $fields, ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'damaged'));
    }

    public function test_one_user_cannot_use_anothers_upload_id(): void
    {
        $course = Course::factory()->create();
        $owner = $this->mentorOf($course);
        $other = $this->mentorOf($course);

        Sanctum::actingAs($owner);
        $fields = $this->sendChunks($this->pdfBytes());

        Sanctum::actingAs($other);
        $this->post('/api/course-materials', ['course_id' => $course->id, 'type' => 'course_content'] + $fields, ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'incomplete'));
        $this->assertSame(0, CourseMaterial::count());
    }

    public function test_chunk_endpoint_validates_input_and_requires_login(): void
    {
        $this->postJson('/api/uploads/chunks', [])->assertStatus(401);

        Sanctum::actingAs(User::factory()->create());
        $id = (string) Str::uuid();

        $this->postJson('/api/uploads/chunks', ['upload_id' => 'nope', 'index' => 0, 'total' => 1])->assertStatus(422);
        $this->post('/api/uploads/chunks', [
            'upload_id' => $id, 'index' => 3, 'total' => 2,
            'chunk' => UploadedFile::fake()->createWithContent('p', 'abc'),
        ], ['Accept' => 'application/json'])->assertStatus(422);
        $this->post('/api/uploads/chunks', [
            'upload_id' => $id, 'index' => 0, 'total' => 1,
            'chunk' => UploadedFile::fake()->create('big', ChunkedUploads::MAX_CHUNK_KB + 1),
        ], ['Accept' => 'application/json'])->assertStatus(422);
        $this->post('/api/uploads/chunks', [
            'upload_id' => $id, 'index' => 0, 'total' => ChunkedUploads::MAX_CHUNKS + 1,
            'chunk' => UploadedFile::fake()->createWithContent('p', 'abc'),
        ], ['Accept' => 'application/json'])->assertStatus(422);
    }

    public function test_a_chunk_can_be_resent_and_an_upload_can_be_cancelled(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        $id = (string) Str::uuid();
        $send = fn () => $this->post('/api/uploads/chunks', [
            'upload_id' => $id, 'index' => 0, 'total' => 2,
            'chunk' => UploadedFile::fake()->createWithContent('p', 'abc'),
        ], ['Accept' => 'application/json']);

        $send()->assertOk()->assertJsonPath('received', 1);
        $send()->assertOk()->assertJsonPath('received', 1);   // retry overwrites, not duplicates

        $this->deleteJson("/api/uploads/{$id}")->assertOk();
        $this->assertSame([], Storage::disk('local')->allFiles('chunks'));
        $this->deleteJson('/api/uploads/not-a-uuid')->assertStatus(404);
    }

    public function test_abandoned_uploads_are_pruned_but_recent_ones_are_kept(): void
    {
        $disk = Storage::disk('local');
        $disk->put('chunks/u1/old/00000.part', 'x');
        $disk->put('chunks/u1/new/00000.part', 'x');
        touch($disk->path('chunks/u1/old/00000.part'), now()->subDays(2)->getTimestamp());

        $this->artisan('uploads:prune-chunks')->assertSuccessful();

        $this->assertFalse($disk->exists('chunks/u1/old/00000.part'));
        $this->assertTrue($disk->exists('chunks/u1/new/00000.part'));
    }
}

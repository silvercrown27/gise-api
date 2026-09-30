<?php

namespace Tests\Feature;

use App\Models\InstructorDocument;
use App\Models\ScholarUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InstructorDocumentControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_requires_authentication(): void
    {
        $response = $this->getJson('/api/instructor-documents');

        $response->assertStatus(401);
    }

    public function test_index_as_instructor_is_scoped_to_own_documents(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        InstructorDocument::factory()->create(['instructor_id' => $instructor->id]);
        InstructorDocument::factory()->create(); // someone else's document
        Sanctum::actingAs($instructor);

        $response = $this->getJson('/api/instructor-documents');

        $response->assertStatus(200);
        $ids = collect($response->json('data.data'))->pluck('instructor_id');
        $this->assertCount(1, $ids);
        $this->assertTrue($ids->contains((string) $instructor->id));
    }

    public function test_index_as_admin_can_filter_by_instructor_id(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        $instructor = User::factory()->create();
        $doc = InstructorDocument::factory()->create(['instructor_id' => $instructor->id]);
        InstructorDocument::factory()->create(); // unrelated document
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/instructor-documents?instructor_id=' . $instructor->id);

        $response->assertStatus(200);
        $ids = collect($response->json('data.data'))->pluck('id');
        $this->assertCount(1, $ids);
        $this->assertTrue($ids->contains((string) $doc->id));
    }

    public function test_index_as_student_is_forbidden(): void
    {
        $student = User::factory()->create();
        ScholarUser::factory()->create(['id' => $student->id, 'role' => 'student']);
        Sanctum::actingAs($student);

        $response = $this->getJson('/api/instructor-documents');

        $response->assertStatus(403);
    }

    public function test_store_requires_authentication(): void
    {
        $response = $this->postJson('/api/instructor-documents', [
            'title' => 'Teaching Certificate',
            'file_url' => 'https://example.com/cert.pdf',
        ]);

        $response->assertStatus(401);
    }

    private function instructor(): User
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        Sanctum::actingAs($instructor);

        return $instructor;
    }

    private function upload(array $overrides = [], ?UploadedFile $file = null)
    {
        return $this->post('/api/instructor-documents', array_merge([
            'title' => 'National ID or passport',
            'document_type' => 'national_id',
            'file' => $file ?? UploadedFile::fake()->create('id.pdf', 100, 'application/pdf'),
        ], $overrides), ['Accept' => 'application/json']);
    }

    public function test_store_as_instructor_saves_the_file_privately(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $instructor = $this->instructor();

        $response = $this->upload([], UploadedFile::fake()->create('My National ID.pdf', 100, 'application/pdf'));

        $response->assertStatus(201)
            ->assertJsonPath('data.instructor_id', (string) $instructor->id)
            ->assertJsonPath('data.original_name', 'My National ID.pdf')
            ->assertJsonPath('data.file_type', 'pdf')
            ->assertJsonPath('data.mime_type', 'application/pdf');

        $document = InstructorDocument::firstOrFail();
        Storage::disk('local')->assertExists($document->path);
        $this->assertEmpty(Storage::disk('public')->allFiles(), 'nothing goes to the public disk');
        $this->assertStringStartsWith("instructor-documents/{$instructor->id}/", $document->path);
        $this->assertStringNotContainsString('My National ID', $document->path, 'the stored name is not the client-supplied one');

        // The storage path never reaches the API response.
        $response->assertJsonMissingPath('data.path')->assertJsonMissingPath('data.file_url');
    }

    public function test_store_rejects_files_that_are_not_pdf_image_or_word(): void
    {
        Storage::fake('local');
        $this->instructor();

        foreach (['evil.html' => 'text/html', 'shell.php' => 'application/x-php', 'logo.svg' => 'image/svg+xml', 'run.exe' => 'application/octet-stream'] as $name => $mime) {
            $this->upload([], UploadedFile::fake()->create($name, 10, $mime))->assertStatus(422);
        }

        $this->assertDatabaseCount('instructor_documents', 0);
        $this->assertEmpty(Storage::disk('local')->allFiles());
    }

    public function test_store_checks_the_contents_not_the_file_name(): void
    {
        Storage::fake('local');
        $this->instructor();

        // An HTML page renamed to .pdf is still HTML. (A real file on disk, not a
        // fake: fakes report their type from the name, real uploads are sniffed.)
        $tmp = tempnam(sys_get_temp_dir(), 'doc');
        file_put_contents($tmp, '<html><script>alert(1)</script></html>');
        $disguised = new UploadedFile($tmp, 'id.pdf', 'application/pdf', null, true);

        $this->upload([], $disguised)->assertStatus(422);
        $this->assertDatabaseCount('instructor_documents', 0);
    }

    public function test_store_rejects_files_over_the_size_limit(): void
    {
        Storage::fake('local');
        $this->instructor();

        $this->upload([], UploadedFile::fake()->create('big.pdf', InstructorDocument::MAX_UPLOAD_KB + 1, 'application/pdf'))
            ->assertStatus(422)
            ->assertJsonPath('message', 'That file is too large. The limit is 10 MB.');
    }

    public function test_store_needs_a_real_file_and_ignores_a_supplied_url(): void
    {
        $this->instructor();

        $this->postJson('/api/instructor-documents', [
            'title' => 'CV', 'document_type' => 'cv', 'file_url' => 'https://evil.example/phish', 'path' => '../../.env',
        ])->assertStatus(422);

        $this->assertDatabaseCount('instructor_documents', 0);
    }

    public function test_store_requires_a_valid_document_type(): void
    {
        Storage::fake('local');
        $this->instructor();

        $this->upload(['document_type' => 'passport_photo'])->assertStatus(422);
        $this->upload(['document_type' => null])->assertStatus(422);
    }

    public function test_store_limits_files_per_required_type_and_in_total(): void
    {
        Storage::fake('local');
        $instructor = $this->instructor();

        InstructorDocument::factory()->count(InstructorDocument::MAX_PER_REQUIRED_TYPE)
            ->create(['instructor_id' => $instructor->id, 'document_type' => 'cv']);
        $this->upload(['document_type' => 'cv', 'title' => 'CV / résumé'])->assertStatus(422);
        $this->upload(['document_type' => 'national_id'])->assertStatus(201);

        InstructorDocument::factory()->count(InstructorDocument::MAX_DOCUMENTS)
            ->create(['instructor_id' => $instructor->id, 'document_type' => 'other']);
        $this->upload(['document_type' => 'other', 'title' => 'Reference letter'])->assertStatus(422);
    }

    public function test_staff_are_notified_once_all_required_documents_are_in(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        $this->instructor();

        $this->upload(['document_type' => 'national_id']);
        $this->upload(['document_type' => 'cv', 'title' => 'CV / résumé']);
        $this->assertDatabaseMissing('notifications', ['user_id' => $admin->id, 'type' => 'system']);

        $this->upload(['document_type' => 'academic_certificate', 'title' => 'Academic certificates']);
        $this->assertDatabaseHas('notifications', ['user_id' => $admin->id, 'type' => 'system']);

        // A later extra file doesn't notify again.
        $this->upload(['document_type' => 'other', 'title' => 'Reference letter']);
        $this->assertSame(1, \App\Models\Notification::where('user_id', $admin->id)->where('type', 'system')->count());
    }

    // ── download ──────────────────────────────────────────────────────────────

    private function storedDocument(User $owner, string $name = 'id.pdf'): InstructorDocument
    {
        $path = "instructor-documents/{$owner->id}/" . fake()->uuid() . '.pdf';
        Storage::disk('local')->put($path, '%PDF-1.4 test');

        return InstructorDocument::factory()->create([
            'instructor_id' => $owner->id, 'path' => $path, 'original_name' => $name, 'file_type' => 'pdf', 'mime_type' => 'application/pdf',
        ]);
    }

    public function test_download_requires_authentication(): void
    {
        Storage::fake('local');
        $document = $this->storedDocument(User::factory()->create());

        $this->getJson("/api/instructor-documents/{$document->id}/download")->assertStatus(401);
    }

    public function test_owner_and_admins_can_download_but_others_cannot(): void
    {
        Storage::fake('local');
        $owner = $this->instructor();
        $document = $this->storedDocument($owner, 'My ID.pdf');

        $response = $this->get("/api/instructor-documents/{$document->id}/download");
        $response->assertStatus(200);
        $this->assertSame('%PDF-1.4 test', $response->streamedContent());
        $this->assertStringContainsString('inline', $response->headers->get('content-disposition'));
        $this->assertSame('nosniff', $response->headers->get('x-content-type-options'));
        $this->assertStringContainsString('no-store', $response->headers->get('cache-control'));

        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        Sanctum::actingAs($admin);
        $this->get("/api/instructor-documents/{$document->id}/download")->assertStatus(200);

        $other = User::factory()->create();
        ScholarUser::factory()->create(['id' => $other->id, 'role' => 'instructor']);
        Sanctum::actingAs($other);
        $this->getJson("/api/instructor-documents/{$document->id}/download")->assertStatus(404);
    }

    public function test_download_of_a_missing_file_is_a_404(): void
    {
        Storage::fake('local');
        $owner = $this->instructor();
        $document = InstructorDocument::factory()->create(['instructor_id' => $owner->id]);
        $legacy = InstructorDocument::factory()->create(['instructor_id' => $owner->id, 'path' => null]);

        $this->getJson("/api/instructor-documents/{$document->id}/download")->assertStatus(404);
        $this->getJson("/api/instructor-documents/{$legacy->id}/download")->assertStatus(404);
    }

    public function test_deleting_a_document_deletes_its_file(): void
    {
        Storage::fake('local');
        $owner = $this->instructor();
        $document = $this->storedDocument($owner);

        $this->deleteJson("/api/instructor-documents/{$document->id}")->assertStatus(200);

        Storage::disk('local')->assertMissing($document->path);
        $this->assertSoftDeleted('instructor_documents', ['id' => $document->id]);
    }

    public function test_store_as_student_is_forbidden(): void
    {
        $student = User::factory()->create();
        ScholarUser::factory()->create(['id' => $student->id, 'role' => 'student']);
        Sanctum::actingAs($student);

        $response = $this->postJson('/api/instructor-documents', [
            'title' => 'Teaching Certificate',
            'file_url' => 'https://example.com/cert.pdf',
        ]);

        $response->assertStatus(403);
    }

    public function test_store_as_admin_is_forbidden(): void
    {
        // Only instructors upload their own documents; admins review, not upload.
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/instructor-documents', [
            'title' => 'Teaching Certificate',
            'file_url' => 'https://example.com/cert.pdf',
        ]);

        $response->assertStatus(403);
    }

    public function test_show_requires_authentication(): void
    {
        $document = InstructorDocument::factory()->create();

        $response = $this->getJson("/api/instructor-documents/{$document->id}");

        $response->assertStatus(401);
    }

    public function test_show_returns_404_for_missing_document(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/instructor-documents/' . fake()->uuid());

        $response->assertStatus(404);
    }

    public function test_show_owner_succeeds(): void
    {
        $instructor = User::factory()->create();
        $document = InstructorDocument::factory()->create(['instructor_id' => $instructor->id]);
        Sanctum::actingAs($instructor);

        $response = $this->getJson("/api/instructor-documents/{$document->id}");

        $response->assertStatus(200);
    }

    public function test_show_admin_succeeds(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        $document = InstructorDocument::factory()->create();
        Sanctum::actingAs($admin);

        $response = $this->getJson("/api/instructor-documents/{$document->id}");

        $response->assertStatus(200);
    }

    public function test_show_non_owner_is_forbidden(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        $document = InstructorDocument::factory()->create();
        Sanctum::actingAs($instructor);

        $response = $this->getJson("/api/instructor-documents/{$document->id}");

        $response->assertStatus(403);
    }

    public function test_delete_requires_authentication(): void
    {
        $document = InstructorDocument::factory()->create();

        $response = $this->deleteJson("/api/instructor-documents/{$document->id}");

        $response->assertStatus(401);
    }

    public function test_delete_owner_succeeds(): void
    {
        $instructor = User::factory()->create();
        $document = InstructorDocument::factory()->create(['instructor_id' => $instructor->id]);
        Sanctum::actingAs($instructor);

        $response = $this->deleteJson("/api/instructor-documents/{$document->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('instructor_documents', ['id' => $document->id]);
    }

    public function test_delete_admin_succeeds(): void
    {
        $admin = User::factory()->create();
        ScholarUser::factory()->create(['id' => $admin->id, 'role' => 'admin']);
        $document = InstructorDocument::factory()->create();
        Sanctum::actingAs($admin);

        $response = $this->deleteJson("/api/instructor-documents/{$document->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('instructor_documents', ['id' => $document->id]);
    }

    public function test_delete_non_owner_is_forbidden(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        $document = InstructorDocument::factory()->create();
        Sanctum::actingAs($instructor);

        $response = $this->deleteJson("/api/instructor-documents/{$document->id}");

        $response->assertStatus(403);
    }
}

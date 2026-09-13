<?php

namespace Tests\Feature;

use App\Models\CourseLesson;
use Database\Seeders\CourseCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseCatalogSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_lessons_have_no_placeholder_content(): void
    {
        $this->seed(CourseCatalogSeeder::class);

        $this->assertGreaterThan(0, CourseLesson::count());
        $this->assertSame(
            0,
            CourseLesson::where('content_url_or_body', 'like', '%will be added by the instructor%')->count()
        );
    }

    public function test_seeded_lessons_all_have_substantive_content(): void
    {
        $this->seed(CourseCatalogSeeder::class);

        $shortLessons = CourseLesson::get()->filter(
            fn (CourseLesson $lesson) => strlen($lesson->content_url_or_body ?? '') < 200
        );

        $this->assertCount(0, $shortLessons, 'Every seeded lesson should have substantive content, not a short stub.');
    }
}

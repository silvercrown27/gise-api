<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Course>
 */
class CourseFactory extends Factory
{
    protected $model = Course::class;

    public function definition(): array
    {
        $title = fake()->unique()->catchPhrase();
        $price = fake()->numberBetween(0, 100000);

        return [
            'instructor_id' => User::factory(),
            'category_id' => null,
            'code' => strtoupper(Str::random(8)),
            'title' => $title,
            'slug' => Str::slug($title) . '-' . fake()->unique()->numberBetween(1000, 999999),
            'tagline' => fake()->sentence(),
            'short_description' => fake()->text(200),
            'full_description' => fake()->paragraphs(3, true),
            'outline' => ['modules' => fake()->words(5)],
            'thumbnail_url' => fake()->imageUrl(),
            'price' => $price,
            'original_price' => $price + fake()->numberBetween(0, 5000),
            'currency' => 'USD',
            'status' => fake()->randomElement(['draft', 'published', 'archived']),
            'level' => fake()->randomElement(['beginner', 'intermediate', 'advanced', 'career_switch']),
            'tag' => fake()->optional()->randomElement(['beginner_friendly', 'high_demand', 'portfolio_track', 'career_switch', 'leadership', 'new']),
            'spine' => fake()->optional()->randomElement(['green', 'blue', 'black', 'bright']),
            'mode' => fake()->randomElement(['physical', 'virtual', 'both']),
            'duration_weeks' => fake()->numberBetween(2, 13),
            'language' => 'en',
            'published_at' => fake()->optional()->dateTimeThisYear(),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Course $course) {
            $course->forceFill(['admin_approval_status' => 'approved']);
        })->afterCreating(function (Course $course) {
            $course->forceFill(['admin_approval_status' => 'approved'])->save();
        });
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'published',
            'published_at' => now(),
        ]);
    }

    public function pendingApproval(): static
    {
        return $this->afterCreating(function (Course $course) {
            $course->forceFill(['admin_approval_status' => 'pending'])->save();
        });
    }

    public function rejected(): static
    {
        return $this->afterCreating(function (Course $course) {
            $course->forceFill([
                'admin_approval_status' => 'rejected',
                'admin_rejection_reason' => fake()->sentence(),
            ])->save();
        });
    }

    public function oLevel(): static
    {
        return $this->state(fn (array $attributes) => [
            'classification' => 'o_level',
            'certificate_kind' => 'recognized',
            'recognized_body' => 'IGCSE',
        ]);
    }

    public function aLevel(): static
    {
        return $this->state(fn (array $attributes) => [
            'classification' => 'a_level',
            'certificate_kind' => 'recognized',
            'recognized_body' => 'IGCSE',
        ]);
    }

    public function skillsProfessional(): static
    {
        return $this->state(fn (array $attributes) => [
            'classification' => 'skills_professional',
            'certificate_kind' => 'completion',
            'recognized_body' => null,
        ]);
    }
}

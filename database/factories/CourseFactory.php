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
            'mode' => fake()->randomElement(['online', 'in_person', 'hybrid']),
            'duration_weeks' => fake()->numberBetween(1, 52),
            'language' => 'en',
            'published_at' => fake()->optional()->dateTimeThisYear(),
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'published',
            'published_at' => now(),
        ]);
    }
}

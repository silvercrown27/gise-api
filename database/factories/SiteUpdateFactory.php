<?php

namespace Database\Factories;

use App\Models\SiteUpdate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SiteUpdate>
 */
class SiteUpdateFactory extends Factory
{
    protected $model = SiteUpdate::class;

    public function definition(): array
    {
        return [
            'type' => fake()->randomElement(['signup', 'subscription', 'new_mentor', 'course_published', 'inquiry', 'complaint']),
            'subject_type' => null,
            'subject_id' => null,
            'causer_id' => null,
            'title' => fake()->sentence(4),
            'description' => fake()->optional()->paragraph(),
            'metadata' => null,
            'is_read' => fake()->boolean(30),
        ];
    }
}

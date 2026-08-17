<?php

namespace Database\Factories;

use App\Models\Testimonial;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Testimonial>
 */
class TestimonialFactory extends Factory
{
    protected $model = Testimonial::class;

    public function definition(): array
    {
        return [
            'course_id' => null,
            'learner_id' => null,
            'name' => fake()->name(),
            'role' => fake()->jobTitle(),
            'quote' => fake()->paragraph(),
            'is_published' => fake()->boolean(80),
            'order_index' => fake()->numberBetween(0, 10),
        ];
    }
}

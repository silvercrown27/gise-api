<?php

namespace Database\Factories;

use App\Models\TeamMember;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TeamMember>
 */
class TeamMemberFactory extends Factory
{
    protected $model = TeamMember::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'role' => fake()->jobTitle(),
            'bio' => fake()->optional()->paragraph(),
            'image_url' => fake()->imageUrl(),
            'order_index' => fake()->numberBetween(0, 10),
            'is_published' => fake()->boolean(80),
        ];
    }
}

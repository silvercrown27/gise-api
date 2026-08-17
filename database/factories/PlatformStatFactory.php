<?php

namespace Database\Factories;

use App\Models\PlatformStat;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlatformStat>
 */
class PlatformStatFactory extends Factory
{
    protected $model = PlatformStat::class;

    public function definition(): array
    {
        return [
            'label' => fake()->words(2, true),
            'value' => (string) fake()->numberBetween(1, 100000),
            'order_index' => fake()->numberBetween(0, 10),
            'is_published' => fake()->boolean(80),
        ];
    }
}

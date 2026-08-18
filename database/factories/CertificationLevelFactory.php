<?php

namespace Database\Factories;

use App\Models\CertificationLevel;
use App\Models\CertificationType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CertificationLevel>
 */
class CertificationLevelFactory extends Factory
{
    protected $model = CertificationLevel::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'certification_type_id' => CertificationType::factory(),
            'name' => $name,
            'slug' => \Illuminate\Support\Str::slug($name) . '-' . fake()->unique()->numberBetween(1000, 9999),
            'description' => fake()->optional()->sentence(),
            'order_index' => fake()->numberBetween(0, 5),
        ];
    }
}

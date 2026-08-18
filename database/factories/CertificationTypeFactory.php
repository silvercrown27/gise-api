<?php

namespace Database\Factories;

use App\Models\CertificationType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CertificationType>
 */
class CertificationTypeFactory extends Factory
{
    protected $model = CertificationType::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => $name,
            'slug' => \Illuminate\Support\Str::slug($name) . '-' . fake()->unique()->numberBetween(1000, 9999),
            'description' => fake()->optional()->sentence(),
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\CertificationLevel;
use App\Models\CertificationPace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CertificationPace>
 */
class CertificationPaceFactory extends Factory
{
    protected $model = CertificationPace::class;

    public function definition(): array
    {
        return [
            'certification_level_id' => CertificationLevel::factory(),
            'name' => fake()->randomElement(['Full certification', 'Partial certification']),
            'certification_track' => fake()->randomElement(['full', 'partial']),
            'duration_weeks' => fake()->numberBetween(8, 52),
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\Certificate;
use App\Models\Enrollment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Certificate>
 */
class CertificateFactory extends Factory
{
    protected $model = Certificate::class;

    public function definition(): array
    {
        return [
            'enrollment_id' => Enrollment::factory(),
            'certificate_number' => strtoupper(fake()->unique()->bothify('CERT-####-????')),
            'certificate_url' => fake()->url(),
            'issued_at' => fake()->dateTimeThisYear(),
        ];
    }
}

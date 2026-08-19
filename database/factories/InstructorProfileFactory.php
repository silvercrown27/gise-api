<?php

namespace Database\Factories;

use App\Models\InstructorProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InstructorProfile>
 */
class InstructorProfileFactory extends Factory
{
    protected $model = InstructorProfile::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'bio' => fake()->paragraph(),
            'expertise_tags' => implode(',', fake()->words(3)),
            'payout_method' => fake()->randomElement(['bank', 'mobile_money', 'paypal']),
            'payout_details' => fake()->bankAccountNumber(),
            'average_rating' => fake()->randomFloat(2, 0, 5),
            'verification_status' => fake()->randomElement(['pending', 'verified']),
            'approval_status' => 'approved',
            'specialization_one' => fake()->words(2, true),
            'specialization_two' => fake()->optional()->words(2, true),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'approval_status' => 'pending',
            'approved_at' => null,
            'approved_by' => null,
        ]);
    }

    public function banned(): static
    {
        return $this->state(fn (array $attributes) => [
            'approval_status' => 'banned',
            'approved_at' => null,
            'approved_by' => null,
        ]);
    }
}

<?php

namespace Database\Factories;

use App\Models\ScholarUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScholarUser>
 */
class ScholarUserFactory extends Factory
{
    protected $model = ScholarUser::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'role' => fake()->randomElement(['learner', 'instructor', 'admin']),
            'phone' => fake()->optional()->phoneNumber(),
            'avatar_url' => fake()->optional()->imageUrl(),
            'status' => fake()->randomElement(['active', 'suspended', 'pending_verification']),
            'last_login_at' => fake()->optional()->dateTimeThisYear(),
        ];
    }

    public function learner(): static
    {
        return $this->state(fn (array $attributes) => ['role' => 'learner']);
    }

    public function instructor(): static
    {
        return $this->state(fn (array $attributes) => ['role' => 'instructor']);
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => ['role' => 'admin']);
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'active']);
    }
}

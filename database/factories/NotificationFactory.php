<?php

namespace Database\Factories;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Notification>
 */
class NotificationFactory extends Factory
{
    protected $model = Notification::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => fake()->randomElement(['payment', 'enrollment', 'certificate', 'rating', 'system']),
            'message' => fake()->sentence(),
            'is_read' => fake()->boolean(30),
        ];
    }
}

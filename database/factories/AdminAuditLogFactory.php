<?php

namespace Database\Factories;

use App\Models\AdminAuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdminAuditLog>
 */
class AdminAuditLogFactory extends Factory
{
    protected $model = AdminAuditLog::class;

    public function definition(): array
    {
        return [
            'admin_id' => User::factory(),
            'action' => fake()->randomElement(['created', 'updated', 'deleted', 'suspended']),
            'target_type' => fake()->randomElement(['user', 'course', 'payment']),
            'target_id' => fake()->uuid(),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}

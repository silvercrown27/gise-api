<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserSettings;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserSettings>
 */
class UserSettingsFactory extends Factory
{
    protected $model = UserSettings::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'key' => fake()->unique()->word(),
            'value' => fake()->word(),
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\Tool;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ToolFactory extends Factory
{
    protected $model = Tool::class;

    public function definition(): array
    {
        $name = fake()->unique()->company() . ' ' . fake()->randomElement(['Studio', 'Pro', 'Desktop', 'Suite']);

        return [
            'name' => $name,
            'slug' => Str::slug($name) . '-' . fake()->unique()->numberBetween(1000, 9999),
            'vendor' => fake()->company(),
            'licence_price' => fake()->numberBetween(20, 400),
            'currency' => 'USD',
            'licence_term' => '12-month student licence',
        ];
    }
}

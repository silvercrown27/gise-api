<?php

namespace Database\Factories;

use App\Models\InstructorDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InstructorDocument>
 */
class InstructorDocumentFactory extends Factory
{
    protected $model = InstructorDocument::class;

    public function definition(): array
    {
        return [
            'instructor_id' => User::factory(),
            'title' => fake()->randomElement(['Teaching Certificate', 'Degree Transcript', 'ID Verification', 'Professional License']),
            'file_url' => '/storage/instructor-documents/' . fake()->uuid() . '.pdf',
            'file_type' => 'pdf',
        ];
    }
}

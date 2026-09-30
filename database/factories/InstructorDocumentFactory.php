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
            'path' => 'instructor-documents/' . fake()->uuid() . '/' . fake()->uuid() . '.pdf',
            'original_name' => 'document.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 12345,
            'file_type' => 'pdf',
        ];
    }
}

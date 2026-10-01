<?php

namespace Database\Factories;

use App\FileUploadStatus;
use App\Models\Program;
use App\Models\ProgramFile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProgramFile>
 */
class ProgramFileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'program_id' => Program::factory(),
            'title' => fake()->sentence(3),
            'original_filename' => fake()->word().'.pdf',
            'storage_disk' => 's3',
            'storage_key' => 'program-files/'.fake()->uuid().'.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'upload_status' => FileUploadStatus::Uploaded,
            'uploaded_at' => now(),
            'metadata' => [],
        ];
    }
}

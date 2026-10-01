<?php

namespace Database\Factories;

use App\FileEmbeddingStatus;
use App\FileUploadStatus;
use App\Models\Subject;
use App\Models\SubjectFile;
use App\SubjectFileType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubjectFile>
 */
class SubjectFileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subject_id' => Subject::factory(),
            'file_type' => SubjectFileType::Other,
            'title' => fake()->sentence(3),
            'original_filename' => fake()->word().'.pdf',
            'storage_disk' => 's3',
            'storage_key' => 'subject-files/'.fake()->uuid().'.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'upload_status' => FileUploadStatus::Uploaded,
            'embedding_status' => FileEmbeddingStatus::Supported,
            'uploaded_at' => now(),
            'metadata' => [],
        ];
    }
}

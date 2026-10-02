<?php

namespace Database\Factories;

use App\CurriculumExtractionStatus;
use App\Models\CurriculumExtraction;
use App\Models\ProgramFile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CurriculumExtraction>
 */
class CurriculumExtractionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'program_file_id' => ProgramFile::factory(),
            'status' => CurriculumExtractionStatus::Reviewing,
            'source_hash' => fake()->sha256(),
            'provider' => 'openai',
            'model' => 'test-model',
            'prompt_version' => 'official-curriculum-v1',
            'proposal' => ['subjects' => []],
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\Subject;
use App\Models\SubjectTopicExtraction;
use App\Models\User;
use App\SubjectTopicExtractionStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SubjectTopicExtraction>
 */
class SubjectTopicExtractionFactory extends Factory
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
            'requested_by' => User::factory(),
            'status' => SubjectTopicExtractionStatus::Reviewing,
            'source_hash' => hash('sha256', fake()->uuid()),
            'source_files' => [],
            'provider' => 'openai',
            'model' => 'gpt-4.1-mini',
            'prompt_version' => 'subject-topics-v1',
            'proposal' => ['topics' => []],
            'correlation_id' => (string) Str::uuid(),
        ];
    }
}

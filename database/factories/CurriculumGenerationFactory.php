<?php

namespace Database\Factories;

use App\CurriculumGenerationKind;
use App\CurriculumGenerationStatus;
use App\Models\CurriculumGeneration;
use App\Models\Program;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CurriculumGeneration>
 */
class CurriculumGenerationFactory extends Factory
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
            'kind' => CurriculumGenerationKind::Subjects,
            'status' => CurriculumGenerationStatus::Queued,
            'ungrounded_requested' => false,
            'request_hash' => hash('sha256', Str::uuid()->toString()),
            'source_hash' => hash('sha256', '[]'),
            'source_files' => [],
            'provider' => 'openai',
            'model' => 'test-model',
            'prompt_version' => 'curriculum-proposal-v1',
            'schema_version' => '1',
            'correlation_id' => (string) Str::uuid(),
        ];
    }
}

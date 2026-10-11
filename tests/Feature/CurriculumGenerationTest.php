<?php

use App\CurriculumGenerationKind;
use App\CurriculumGenerationStatus;
use App\Models\CurriculumGeneration;
use App\Models\Program;
use App\Models\Subject;
use App\Models\SyllabusTopic;

test('curriculum generations keep their scope and review snapshots', function () {
    $program = Program::factory()->create();
    $subject = Subject::factory()->for($program)->create();
    $topic = SyllabusTopic::factory()->for($subject)->create();

    $generation = CurriculumGeneration::factory()
        ->for($program)
        ->for($subject)
        ->for($topic, 'syllabusTopic')
        ->create([
            'kind' => CurriculumGenerationKind::Concepts,
            'status' => CurriculumGenerationStatus::Reviewing,
            'source_files' => [[
                'id' => 12,
                'content_hash' => hash('sha256', 'source text'),
                'title' => 'Reference PDF',
                'file_type' => 'official_reference',
                'subject_id' => $subject->id,
            ]],
            'proposal' => ['items' => [['draft_id' => 'concept-1', 'title' => 'Double-entry accounting']]],
        ]);

    expect($generation->kind)->toBe(CurriculumGenerationKind::Concepts)
        ->and($generation->status)->toBe(CurriculumGenerationStatus::Reviewing)
        ->and($generation->program->is($program))->toBeTrue()
        ->and($generation->subject->is($subject))->toBeTrue()
        ->and($generation->syllabusTopic->is($topic))->toBeTrue()
        ->and($generation->source_files[0]['content_hash'])->toBe(hash('sha256', 'source text'))
        ->and($generation->proposal['items'][0]['draft_id'])->toBe('concept-1');
});

<?php

use App\CurriculumExtractionStatus;
use App\FileEmbeddingStatus;
use App\Models\CurriculumExtraction;
use App\Models\Program;
use App\Models\ProgramFile;
use App\Models\Subject;
use App\Models\SyllabusTopic;
use App\Models\User;
use App\Services\OfficialCurriculumExtractionService;
use Illuminate\Validation\ValidationException;
use Mockery\MockInterface;

function curriculumReviewData(array $topics = []): array
{
    return [
        'subjects' => [[
            'code' => 'BIO-1',
            'name' => 'Biology',
            'description' => 'The study of living systems.',
            'source_page' => 2,
            'topics' => $topics,
        ]],
    ];
}

test('a reviewer can publish selected official subjects and their topic hierarchy', function () {
    $program = Program::factory()->create();
    $file = ProgramFile::factory()->for($program)->create([
        'content_hash' => str_repeat('a', 64),
        'embedding_status' => FileEmbeddingStatus::Complete,
    ]);
    $reviewer = User::factory()->admin()->create();
    $extraction = CurriculumExtraction::factory()->for($file)->create([
        'source_hash' => $file->content_hash,
        'proposal' => curriculumReviewData([
            ['code' => 'BIO-1.1', 'title' => 'Cells', 'description' => null, 'source_page' => 2, 'parent_index' => null],
            ['code' => 'BIO-1.1.1', 'title' => 'Cell structure', 'description' => null, 'source_page' => 3, 'parent_index' => 0],
        ]),
    ]);

    app(OfficialCurriculumExtractionService::class)->publish($extraction, [[
        'include' => '1',
        'name' => 'Biology',
        'code' => 'BIO-1',
        'description' => 'The study of living systems.',
        'topics' => [
            ['include' => '1', 'code' => 'BIO-1.1', 'title' => 'Cells', 'description' => 'Cell systems'],
            ['include' => '1', 'code' => 'BIO-1.1.1', 'title' => 'Cell structure', 'description' => 'Parts of a cell'],
        ],
    ]], $reviewer);

    $subject = Subject::query()->where('program_id', $program->id)->where('code', 'BIO-1')->firstOrFail();
    $parent = SyllabusTopic::query()->where('subject_id', $subject->id)->where('code', 'BIO-1.1')->firstOrFail();
    $child = SyllabusTopic::query()->where('subject_id', $subject->id)->where('code', 'BIO-1.1.1')->firstOrFail();

    expect($child->parent_id)->toBe($parent->id)
        ->and($extraction->fresh()->status)->toBe(CurriculumExtractionStatus::Published)
        ->and($extraction->fresh()->reviewed_by)->toBe($reviewer->id)
        ->and($extraction->fresh()->proposal['subjects'][0]['source_page'])->toBe(2)
        ->and($extraction->fresh()->reviewed_proposal['subjects'][0]['topics'][1]['source_page'])->toBe(3)
        ->and($subject->concepts()->count())->toBe(0);
});

test('a reviewer can publish subjects and topics without official codes', function () {
    $program = Program::factory()->create();
    $file = ProgramFile::factory()->for($program)->create([
        'content_hash' => str_repeat('c', 64),
        'embedding_status' => FileEmbeddingStatus::Complete,
    ]);
    $reviewer = User::factory()->admin()->create();
    $extraction = CurriculumExtraction::factory()->for($file)->create([
        'source_hash' => $file->content_hash,
        'proposal' => curriculumReviewData([
            ['code' => null, 'title' => 'Living systems', 'description' => 'Describes the variety of organisms and how they interact.', 'description_origin' => 'ai_generated', 'source_page' => 2, 'parent_index' => null],
        ]),
    ]);

    app(OfficialCurriculumExtractionService::class)->publish($extraction, [[
        'include' => '1',
        'name' => 'Biology',
        'code' => '',
        'description' => 'Studies living systems.',
        'topics' => [
            ['include' => '1', 'code' => '', 'title' => 'Living systems', 'description' => 'Describes the variety of organisms and how they interact.'],
        ],
    ]], $reviewer);

    $subject = Subject::query()->where('program_id', $program->id)->firstOrFail();
    $topic = SyllabusTopic::query()->where('subject_id', $subject->id)->firstOrFail();

    expect($subject->code)->toBeNull()
        ->and($subject->description)->toBe('Studies living systems.')
        ->and($topic->code)->toBeNull()
        ->and($topic->description)->toBe('Describes the variety of organisms and how they interact.')
        ->and($extraction->fresh()->reviewed_proposal['subjects'][0]['topics'][0]['description_origin'])->toBe('ai_generated')
        ->and($extraction->fresh()->status)->toBe(CurriculumExtractionStatus::Published);
});

test('publishing a child topic without its selected parent is rejected atomically', function () {
    $program = Program::factory()->create();
    $file = ProgramFile::factory()->for($program)->create([
        'content_hash' => str_repeat('b', 64),
        'embedding_status' => FileEmbeddingStatus::Complete,
    ]);
    $reviewer = User::factory()->admin()->create();
    $extraction = CurriculumExtraction::factory()->for($file)->create([
        'source_hash' => $file->content_hash,
        'proposal' => curriculumReviewData([
            ['code' => 'BIO-1.1', 'title' => 'Cells', 'description' => null, 'source_page' => 2, 'parent_index' => null],
            ['code' => 'BIO-1.1.1', 'title' => 'Cell structure', 'description' => null, 'source_page' => 3, 'parent_index' => 0],
        ]),
    ]);

    expect(fn () => app(OfficialCurriculumExtractionService::class)->publish($extraction, [[
        'include' => '1',
        'name' => 'Biology',
        'code' => 'BIO-1',
        'topics' => [
            ['include' => '0', 'code' => 'BIO-1.1', 'title' => 'Cells'],
            ['include' => '1', 'code' => 'BIO-1.1.1', 'title' => 'Cell structure'],
        ],
    ]], $reviewer))->toThrow(ValidationException::class);

    $this->assertDatabaseCount('subjects', 0);
    $this->assertDatabaseCount('syllabus_topics', 0);
    expect($extraction->fresh()->status)->toBe(CurriculumExtractionStatus::Reviewing);
});

test('rejecting a proposal records the reviewer without creating curriculum records', function () {
    $extraction = CurriculumExtraction::factory()->create();
    $reviewer = User::factory()->admin()->create();

    app(OfficialCurriculumExtractionService::class)->reject($extraction, $reviewer);

    expect($extraction->fresh()->status)->toBe(CurriculumExtractionStatus::Rejected)
        ->and($extraction->fresh()->reviewed_by)->toBe($reviewer->id);
    $this->assertDatabaseCount('subjects', 0);
    $this->assertDatabaseCount('syllabus_topics', 0);
});

test('an administrator can queue extraction from a file with completed embeddings', function () {
    $file = ProgramFile::factory()->create(['embedding_status' => FileEmbeddingStatus::Complete]);
    $administrator = User::factory()->admin()->create();
    $extraction = CurriculumExtraction::factory()->for($file)->create();

    $this->mock(OfficialCurriculumExtractionService::class, function (MockInterface $service) use ($administrator, $file, $extraction): void {
        $service->shouldReceive('isAvailable')->once()->andReturnTrue();
        $service->shouldReceive('queue')->once()->withArgs(fn (ProgramFile $queuedFile, User $requester): bool => $queuedFile->is($file) && $requester->is($administrator))->andReturn($extraction);
    });

    $this->actingAs($administrator)
        ->post(route('program-files.curriculum-extractions.store', $file), ['confirmation' => '1'])
        ->assertRedirect(route('curriculum-extractions.show', $extraction));
});

test('curriculum extraction is hidden when the feature is unavailable', function () {
    $file = ProgramFile::factory()->create(['embedding_status' => FileEmbeddingStatus::Complete]);

    $this->mock(OfficialCurriculumExtractionService::class, function (MockInterface $service): void {
        $service->shouldReceive('isAvailable')->once()->andReturnFalse();
        $service->shouldNotReceive('queue');
    });

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('program-files.curriculum-extractions.store', $file), ['confirmation' => '1'])
        ->assertNotFound();
});

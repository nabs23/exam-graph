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

function curriculumReviewData(array $topics = [], int $sourceFileId = 1): array
{
    return [
        'subjects' => [[
            'code' => 'BIO-1',
            'name' => 'Biology',
            'description' => 'The study of living systems.',
            'source_file_id' => $sourceFileId,
            'source_page' => 2,
            'topics' => array_map(fn (array $topic): array => ['source_file_id' => $sourceFileId, ...$topic], $topics),
        ]],
    ];
}

function curriculumSourceSnapshot(ProgramFile $file): array
{
    return [['id' => $file->id, 'content_hash' => $file->content_hash, 'title' => $file->title]];
}

test('a reviewer can publish selected official subjects and their topic hierarchy', function () {
    $program = Program::factory()->create();
    $file = ProgramFile::factory()->for($program)->create([
        'content_hash' => str_repeat('a', 64),
        'embedding_status' => FileEmbeddingStatus::Complete,
    ]);
    $reviewer = User::factory()->admin()->create();
    $extraction = CurriculumExtraction::factory()->for($program)->for($file)->create([
        'source_hash' => $file->content_hash,
        'source_files' => curriculumSourceSnapshot($file),
        'proposal' => curriculumReviewData([
            ['code' => 'BIO-1.1', 'title' => 'Cells', 'description' => null, 'source_page' => 2, 'parent_index' => null],
            ['code' => 'BIO-1.1.1', 'title' => 'Cell structure', 'description' => null, 'source_page' => 3, 'parent_index' => 0],
        ], $file->id),
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
    $extraction = CurriculumExtraction::factory()->for($program)->for($file)->create([
        'source_hash' => $file->content_hash,
        'source_files' => curriculumSourceSnapshot($file),
        'proposal' => curriculumReviewData([
            ['code' => null, 'title' => 'Living systems', 'description' => 'Describes the variety of organisms and how they interact.', 'description_origin' => 'ai_generated', 'source_page' => 2, 'parent_index' => null],
        ], $file->id),
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

test('publishing reuses existing subjects and topics before adding missing topics', function () {
    $program = Program::factory()->create();
    $file = ProgramFile::factory()->for($program)->create([
        'content_hash' => str_repeat('d', 64),
        'embedding_status' => FileEmbeddingStatus::Complete,
    ]);
    $subject = Subject::factory()->for($program)->create(['code' => 'BIO-1', 'name' => 'Biology']);
    $existingTopic = SyllabusTopic::factory()->for($subject)->create(['code' => 'BIO-1.1', 'title' => 'Cells']);
    $extraction = CurriculumExtraction::factory()->for($program)->for($file)->create([
        'source_hash' => $file->content_hash,
        'source_files' => curriculumSourceSnapshot($file),
        'proposal' => curriculumReviewData([
            ['code' => 'BIO-1.1', 'title' => 'Cells', 'description' => null, 'source_page' => 2, 'parent_index' => null],
            ['code' => 'BIO-1.1.1', 'title' => 'Cell structure', 'description' => null, 'source_page' => 3, 'parent_index' => 0],
        ], $file->id),
    ]);

    app(OfficialCurriculumExtractionService::class)->publish($extraction, [[
        'include' => '1',
        'name' => 'Biology',
        'code' => 'BIO-1',
        'topics' => [
            ['include' => '1', 'code' => 'BIO-1.1', 'title' => 'Cells'],
            ['include' => '1', 'code' => 'BIO-1.1.1', 'title' => 'Cell structure'],
        ],
    ]], User::factory()->admin()->create());

    $childTopic = SyllabusTopic::query()->where('subject_id', $subject->id)->where('code', 'BIO-1.1.1')->firstOrFail();

    $this->assertDatabaseCount('subjects', 1);
    $this->assertDatabaseCount('syllabus_topics', 2);
    expect($childTopic->parent_id)->toBe($existingTopic->id)
        ->and($extraction->fresh()->status)->toBe(CurriculumExtractionStatus::Published);
});

test('publishing a child topic without its selected parent is rejected atomically', function () {
    $program = Program::factory()->create();
    $file = ProgramFile::factory()->for($program)->create([
        'content_hash' => str_repeat('b', 64),
        'embedding_status' => FileEmbeddingStatus::Complete,
    ]);
    $reviewer = User::factory()->admin()->create();
    $extraction = CurriculumExtraction::factory()->for($program)->for($file)->create([
        'source_hash' => $file->content_hash,
        'source_files' => curriculumSourceSnapshot($file),
        'proposal' => curriculumReviewData([
            ['code' => 'BIO-1.1', 'title' => 'Cells', 'description' => null, 'source_page' => 2, 'parent_index' => null],
            ['code' => 'BIO-1.1.1', 'title' => 'Cell structure', 'description' => null, 'source_page' => 3, 'parent_index' => 0],
        ], $file->id),
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

test('publishing fails when any file in the program source snapshot changes', function () {
    $program = Program::factory()->create();
    $firstFile = ProgramFile::factory()->for($program)->create([
        'content_hash' => str_repeat('a', 64),
        'embedding_status' => FileEmbeddingStatus::Complete,
    ]);
    $secondFile = ProgramFile::factory()->for($program)->create([
        'content_hash' => str_repeat('b', 64),
        'embedding_status' => FileEmbeddingStatus::Complete,
    ]);
    $extraction = CurriculumExtraction::factory()->for($program)->create([
        'source_files' => [
            ...curriculumSourceSnapshot($firstFile),
            ...curriculumSourceSnapshot($secondFile),
        ],
        'proposal' => curriculumReviewData([], $firstFile->id),
    ]);

    $secondFile->forceFill(['content_hash' => str_repeat('c', 64)])->save();

    expect(fn () => app(OfficialCurriculumExtractionService::class)->publish($extraction, [[
        'include' => '1',
        'name' => 'Biology',
        'code' => 'BIO-1',
        'description' => 'The study of living systems.',
        'topics' => [],
    ]], User::factory()->admin()->create()))->toThrow(ValidationException::class);

    expect($extraction->fresh()->status)->toBe(CurriculumExtractionStatus::Failed);
    $this->assertDatabaseCount('subjects', 0);
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

test('an administrator can queue extraction from a program with completed embeddings', function () {
    $program = Program::factory()->create();
    ProgramFile::factory()->for($program)->create(['embedding_status' => FileEmbeddingStatus::Complete]);
    $administrator = User::factory()->admin()->create();
    $extraction = CurriculumExtraction::factory()->for($program)->create();

    $this->mock(OfficialCurriculumExtractionService::class, function (MockInterface $service) use ($administrator, $program, $extraction): void {
        $service->shouldReceive('isAvailable')->once()->andReturnTrue();
        $service->shouldReceive('queue')->once()->withArgs(fn (Program $queuedProgram, User $requester): bool => $queuedProgram->is($program) && $requester->is($administrator))->andReturn($extraction);
    });

    $this->actingAs($administrator)
        ->post(route('programs.curriculum-extractions.store', $program), ['confirmation' => '1'])
        ->assertRedirect(route('curriculum-extractions.show', $extraction));
});

test('an administrator can view all curriculum extractions for a program', function () {
    $program = Program::factory()->create();
    $requester = User::factory()->admin()->create();
    $extraction = CurriculumExtraction::factory()->for($program)->create([
        'requested_by' => $requester->id,
        'source_files' => [[
            'id' => 100,
            'title' => 'Official syllabus.pdf',
            'content_hash' => str_repeat('d', 64),
        ]],
    ]);
    CurriculumExtraction::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('programs.curriculum-extractions.index', $program))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/curriculum-extractions/index')
            ->where('program.id', $program->id)
            ->has('extractions', 1)
            ->where('extractions.0.id', $extraction->id)
            ->where('extractions.0.source_files.0.title', 'Official syllabus.pdf')
            ->where('extractions.0.requester.name', $requester->name));
});

test('an administrator can delete failed and unreviewed curriculum extractions', function () {
    $program = Program::factory()->create();
    $failed = CurriculumExtraction::factory()->for($program)->create([
        'status' => CurriculumExtractionStatus::Failed,
        'reviewed_proposal' => ['subjects' => []],
    ]);
    $unreviewed = CurriculumExtraction::factory()->for($program)->create([
        'status' => CurriculumExtractionStatus::Reviewing,
        'reviewed_proposal' => null,
    ]);
    $user = User::factory()->admin()->create();

    $this->actingAs($user)
        ->delete(route('programs.curriculum-extractions.destroy', [$program, $failed]))
        ->assertRedirect();
    $this->actingAs($user)
        ->delete(route('programs.curriculum-extractions.destroy', [$program, $unreviewed]))
        ->assertRedirect();

    $this->assertDatabaseMissing('curriculum_extractions', ['id' => $failed->id]);
    $this->assertDatabaseMissing('curriculum_extractions', ['id' => $unreviewed->id]);
});

test('an administrator cannot delete a reviewed curriculum extraction', function () {
    $program = Program::factory()->create();
    $extraction = CurriculumExtraction::factory()->for($program)->create([
        'status' => CurriculumExtractionStatus::Published,
        'reviewed_proposal' => ['subjects' => []],
    ]);

    $this->actingAs(User::factory()->admin()->create())
        ->delete(route('programs.curriculum-extractions.destroy', [$program, $extraction]))
        ->assertStatus(422);

    $this->assertDatabaseHas('curriculum_extractions', ['id' => $extraction->id]);
});

test('curriculum extraction is hidden when the feature is unavailable', function () {
    $program = Program::factory()->create();

    $this->mock(OfficialCurriculumExtractionService::class, function (MockInterface $service): void {
        $service->shouldReceive('isAvailable')->once()->andReturnFalse();
        $service->shouldNotReceive('queue');
    });

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('programs.curriculum-extractions.store', $program), ['confirmation' => '1'])
        ->assertNotFound();
});

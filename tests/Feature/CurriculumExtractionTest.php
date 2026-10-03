<?php

use App\CurriculumExtractionStatus;
use App\FileEmbeddingStatus;
use App\Models\Concept;
use App\Models\CurriculumExtraction;
use App\Models\Program;
use App\Models\ProgramFile;
use App\Models\Subject;
use App\Models\SubjectFile;
use App\Models\SyllabusTopic;
use App\Models\User;
use App\Services\OfficialCurriculumExtractionService;
use Illuminate\Support\Facades\Storage;
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

test('a reviewer can publish selected subjects and their topic hierarchy with optional citations', function (bool $hasCitation) {
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

    if (! $hasCitation) {
        $proposal = $extraction->proposal;
        $proposal['subjects'][0]['source_file_id'] = null;
        $proposal['subjects'][0]['source_page'] = null;
        foreach ($proposal['subjects'][0]['topics'] as &$topic) {
            $topic['source_file_id'] = null;
            $topic['source_page'] = null;
        }
        unset($topic);
        $extraction->forceFill(['proposal' => $proposal])->save();
    }

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
        ->and($extraction->fresh()->proposal['subjects'][0]['source_page'])->toBe($hasCitation ? 2 : null)
        ->and($extraction->fresh()->reviewed_proposal['subjects'][0]['topics'][1]['source_page'])->toBe($hasCitation ? 3 : null)
        ->and($subject->concepts()->count())->toBe(0);
})->with(['cited' => true, 'uncited' => false]);

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
        $service->makePartial();
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
        $service->makePartial();
        $service->shouldReceive('isAvailable')->once()->andReturnFalse();
        $service->shouldNotReceive('queue');
    });

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('programs.curriculum-extractions.store', $program), ['confirmation' => '1'])
        ->assertNotFound();
});

test('publishing matches subjects and topics regardless of case and optional codes', function (?string $existingCode, ?string $submittedCode, ?string $expectedCode) {
    $program = Program::factory()->create();
    $file = ProgramFile::factory()->for($program)->create(['embedding_status' => FileEmbeddingStatus::Complete]);
    $subject = Subject::factory()->for($program)->create(['name' => 'Biology', 'code' => $existingCode]);
    $topic = SyllabusTopic::factory()->for($subject)->create(['title' => 'Cells', 'code' => $existingCode, 'parent_id' => null]);
    $extraction = CurriculumExtraction::factory()->for($program)->for($file)->create([
        'source_files' => curriculumSourceSnapshot($file),
        'proposal' => curriculumReviewData([
            ['code' => $submittedCode, 'title' => 'CELLS', 'description' => null, 'source_page' => 2, 'parent_index' => null],
            ['code' => null, 'title' => 'Cell structure', 'description' => null, 'source_page' => 2, 'parent_index' => 0],
        ], $file->id),
    ]);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('curriculum-extractions.publish', $extraction), ['subjects' => [[
            'include' => true, 'name' => ' BIOLOGY ', 'code' => $submittedCode,
            'topics' => [
                ['include' => true, 'title' => ' CELLS ', 'code' => $submittedCode],
                ['include' => true, 'title' => 'Cell structure', 'code' => null],
            ],
        ]]])
        ->assertSessionHasNoErrors();

    $this->assertDatabaseCount('subjects', 1);
    $this->assertDatabaseCount('syllabus_topics', 2);
    $this->assertDatabaseHas('subjects', ['id' => $subject->id, 'name' => 'Biology', 'code' => $expectedCode]);
    $this->assertDatabaseHas('syllabus_topics', ['id' => $topic->id, 'title' => 'Cells', 'code' => $expectedCode]);
    $this->assertDatabaseHas('syllabus_topics', ['title' => 'Cell structure', 'parent_id' => $topic->id, 'subject_id' => $subject->id]);
    expect($extraction->fresh()->status)->toBe(CurriculumExtractionStatus::Published);
})->with([
    'no codes' => [null, null, null],
    'case-only codes' => ['BIO', 'bio', 'BIO'],
    'omitted codes' => ['BIO', null, 'BIO'],
    'new codes' => [null, 'BIO', 'BIO'],
]);

test('publishing rejects ambiguous subjects and conflicting codes atomically', function (array $records, ?string $code, string $message) {
    $program = Program::factory()->create();
    $file = ProgramFile::factory()->for($program)->create(['embedding_status' => FileEmbeddingStatus::Complete]);
    foreach ($records as $record) {
        Subject::factory()->for($program)->create($record);
    }
    $extraction = CurriculumExtraction::factory()->for($program)->for($file)->create([
        'source_files' => curriculumSourceSnapshot($file), 'proposal' => curriculumReviewData([], $file->id),
    ]);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('curriculum-extractions.publish', $extraction), ['subjects' => [[
            'include' => true, 'name' => 'Biology', 'code' => $code, 'topics' => [],
        ]]])
        ->assertSessionHasErrors(['subjects' => $message]);

    $this->assertDatabaseCount('subjects', count($records));
    $this->assertDatabaseCount('syllabus_topics', 0);
    expect($extraction->fresh()->status)->toBe(CurriculumExtractionStatus::Reviewing);
})->with([
    'duplicate names' => [[['name' => 'Biology', 'code' => null], ['name' => 'BIOLOGY', 'code' => null]], null, 'Multiple existing records match "Biology". Resolve the duplicates before publishing.'],
    'conflicting code' => [[['name' => 'BIOLOGY', 'code' => 'BIO-1']], 'BIO-2', 'The code for "Biology" conflicts with its existing code. Review the code before publishing.'],
    'code and name identify different records' => [[['name' => 'Chemistry', 'code' => 'BIO-1'], ['name' => 'Biology', 'code' => null]], 'bio-1', 'Multiple existing records match "Biology". Resolve the duplicates before publishing.'],
]);

test('publishing rejects topic conflicts and rolls back earlier inserts', function (array $existingTopics, string $message) {
    $program = Program::factory()->create();
    $file = ProgramFile::factory()->for($program)->create(['embedding_status' => FileEmbeddingStatus::Complete]);
    $subject = Subject::factory()->for($program)->create(['name' => 'Biology', 'code' => null]);
    $parent = SyllabusTopic::factory()->for($subject)->create(['title' => 'Existing parent', 'code' => null, 'parent_id' => null]);
    foreach ($existingTopics as $existingTopic) {
        SyllabusTopic::factory()->for($subject)->create([
            ...$existingTopic, 'parent_id' => $existingTopic['parent_id'] === 'parent' ? $parent->id : null,
        ]);
    }
    $extraction = CurriculumExtraction::factory()->for($program)->for($file)->create([
        'source_files' => curriculumSourceSnapshot($file),
        'proposal' => curriculumReviewData([
            ['title' => 'New topic', 'source_page' => null, 'parent_index' => null],
            ['title' => 'Cells', 'source_page' => null, 'parent_index' => null],
        ], $file->id),
    ]);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('curriculum-extractions.publish', $extraction), ['subjects' => [[
            'include' => true, 'name' => 'Biology', 'code' => 'BIO', 'topics' => [
                ['include' => true, 'title' => 'New topic', 'code' => null],
                ['include' => true, 'title' => 'Cells', 'code' => 'CELL'],
            ],
        ]]])
        ->assertSessionHasErrors(['subjects' => $message]);

    $this->assertDatabaseCount('syllabus_topics', count($existingTopics) + 1);
    $this->assertDatabaseHas('subjects', ['id' => $subject->id, 'code' => null]);
    expect($extraction->fresh()->status)->toBe(CurriculumExtractionStatus::Reviewing);
})->with([
    'ambiguous siblings' => [[['title' => 'Cells', 'code' => null, 'parent_id' => null], ['title' => 'CELLS', 'code' => null, 'parent_id' => null]], 'Multiple existing records match "Cells". Resolve the duplicates before publishing.'],
    'conflicting code' => [[['title' => 'CELLS', 'code' => 'OTHER', 'parent_id' => null]], 'The code for "Cells" conflicts with its existing code. Review the code before publishing.'],
    'conflicting hierarchy' => [[['title' => 'Cells', 'code' => 'cell', 'parent_id' => 'parent']], 'The topic "Cells" already exists under a different parent. Review its hierarchy before publishing.'],
]);

test('publishing rejects repeated subject names in one selection', function () {
    $program = Program::factory()->create();
    $file = ProgramFile::factory()->for($program)->create(['embedding_status' => FileEmbeddingStatus::Complete]);
    $proposal = curriculumReviewData([], $file->id);
    $proposal['subjects'][] = $proposal['subjects'][0];
    $extraction = CurriculumExtraction::factory()->for($program)->for($file)->create([
        'source_files' => curriculumSourceSnapshot($file), 'proposal' => $proposal,
    ]);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('curriculum-extractions.publish', $extraction), ['subjects' => [
            ['include' => true, 'name' => 'Biology', 'code' => null],
            ['include' => true, 'name' => 'BIOLOGY', 'code' => null],
        ]])
        ->assertSessionHasErrors(['subjects' => 'Select each subject name only once, regardless of letter case.']);

    $this->assertDatabaseCount('subjects', 0);
    expect($extraction->fresh()->status)->toBe(CurriculumExtractionStatus::Reviewing);
});

test('publishing rejects repeated sibling topic titles in one selection', function () {
    $program = Program::factory()->create();
    $file = ProgramFile::factory()->for($program)->create(['embedding_status' => FileEmbeddingStatus::Complete]);
    $extraction = CurriculumExtraction::factory()->for($program)->for($file)->create([
        'source_files' => curriculumSourceSnapshot($file),
        'proposal' => curriculumReviewData([
            ['title' => 'Cells', 'source_page' => null, 'parent_index' => null],
            ['title' => 'CELLS', 'source_page' => null, 'parent_index' => null],
        ], $file->id),
    ]);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('curriculum-extractions.publish', $extraction), ['subjects' => [[
            'include' => true, 'name' => 'Biology', 'code' => null, 'topics' => [
                ['include' => true, 'title' => 'Cells', 'code' => null],
                ['include' => true, 'title' => 'CELLS', 'code' => null],
            ],
        ]]])
        ->assertSessionHasErrors(['subjects' => 'Select each topic title only once under the same parent, regardless of letter case.']);

    $this->assertDatabaseCount('subjects', 0);
    $this->assertDatabaseCount('syllabus_topics', 0);
    expect($extraction->fresh()->status)->toBe(CurriculumExtractionStatus::Reviewing);
});

test('a reviewer can preview and merge selected duplicate subjects while publishing', function () {
    Storage::fake('local');
    $program = Program::factory()->create();
    $file = ProgramFile::factory()->for($program)->create([
        'content_hash' => str_repeat('e', 64), 'embedding_status' => FileEmbeddingStatus::Complete,
    ]);
    $target = Subject::factory()->for($program)->create(['name' => 'Financial Accounting and Reporting', 'code' => 'FAR']);
    $duplicate = Subject::factory()->for($program)->create(['name' => 'FINANCIAL ACCOUNTING AND REPORTING', 'code' => null]);
    $keptTopic = SyllabusTopic::factory()->for($target)->create(['title' => 'Cash', 'code' => 'FAR-1', 'parent_id' => null]);
    $duplicateTopic = SyllabusTopic::factory()->for($duplicate)->create(['title' => 'CASH', 'code' => 'far-1', 'parent_id' => null]);
    $extraTopic = SyllabusTopic::factory()->for($duplicate)->create(['title' => 'Banks', 'code' => null, 'parent_id' => null]);
    $concept = Concept::factory()->for($duplicate)->create(['syllabus_topic_id' => $duplicateTopic->id]);
    $subjectFile = SubjectFile::factory()->for($duplicate)->create();
    $extraction = CurriculumExtraction::factory()->for($program)->for($file)->create([
        'source_hash' => $file->content_hash,
        'source_files' => curriculumSourceSnapshot($file),
        'proposal' => curriculumReviewData([], $file->id),
    ]);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('curriculum-extractions.show', $extraction))
        ->assertInertia(fn ($page) => $page
            ->component('admin/curriculum-extractions/show')
            ->where('existingSubjects.0.id', $target->id)
            ->where('existingSubjects.0.topics.0.title', 'Cash')
            ->where('existingSubjects.1.id', $duplicate->id));

    $this->actingAs($admin)
        ->post(route('curriculum-extractions.publish', $extraction), ['subjects' => [[
            'include' => true,
            'name' => 'Financial Accounting and Reporting',
            'code' => 'FAR',
            'merge_target_id' => $target->id,
            'merge_source_ids' => [$duplicate->id],
            'topics' => [],
        ]]])
        ->assertSessionHasNoErrors();

    $this->assertModelMissing($duplicate);
    $this->assertModelMissing($duplicateTopic);
    $this->assertDatabaseHas('subjects', ['id' => $target->id, 'name' => 'Financial Accounting and Reporting', 'code' => 'FAR']);
    $this->assertDatabaseHas('syllabus_topics', ['id' => $keptTopic->id, 'subject_id' => $target->id]);
    $this->assertDatabaseHas('syllabus_topics', ['id' => $extraTopic->id, 'subject_id' => $target->id]);
    $this->assertDatabaseHas('concepts', ['id' => $concept->id, 'subject_id' => $target->id, 'syllabus_topic_id' => $keptTopic->id]);
    $this->assertDatabaseHas('subject_files', ['id' => $subjectFile->id, 'subject_id' => $target->id]);
    $this->assertDatabaseCount('subjects', 1);
    expect(Storage::disk('local')->files('curriculum-merges'))->toHaveCount(1);
});

test('a reviewer can manually choose a matching subject and leave another coded subject separate', function () {
    $program = Program::factory()->create();
    $file = ProgramFile::factory()->for($program)->create([
        'content_hash' => str_repeat('1', 64), 'embedding_status' => FileEmbeddingStatus::Complete,
    ]);
    $target = Subject::factory()->for($program)->create(['name' => 'Financial Accounting and Reporting', 'code' => 'FAR']);
    $codedDuplicate = Subject::factory()->for($program)->create(['name' => 'FINANCIAL ACCOUNTING AND REPORTING', 'code' => 'FAR2']);
    $uncodedDuplicate = Subject::factory()->for($program)->create(['name' => 'FINANCIAL ACCOUNTING AND REPORTING', 'code' => null]);
    $extraction = CurriculumExtraction::factory()->for($program)->for($file)->create([
        'source_hash' => $file->content_hash,
        'source_files' => curriculumSourceSnapshot($file),
        'proposal' => curriculumReviewData([], $file->id),
    ]);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('curriculum-extractions.publish', $extraction), ['subjects' => [[
            'include' => true,
            'name' => 'Financial Accounting and Reporting',
            'code' => 'FAR',
            'merge_target_id' => $target->id,
            'merge_source_ids' => [],
            'topics' => [],
        ]]])
        ->assertSessionHasNoErrors();

    $this->assertModelExists($target);
    $this->assertModelExists($codedDuplicate);
    $this->assertModelExists($uncodedDuplicate);
    $this->assertDatabaseCount('subjects', 3);
    expect($extraction->fresh()->status)->toBe(CurriculumExtractionStatus::Published);
});

test('publishing does not merge subjects whose existing codes conflict', function () {
    Storage::fake('local');
    $program = Program::factory()->create();
    $file = ProgramFile::factory()->for($program)->create([
        'content_hash' => str_repeat('2', 64), 'embedding_status' => FileEmbeddingStatus::Complete,
    ]);
    $target = Subject::factory()->for($program)->create(['name' => 'Financial Accounting and Reporting', 'code' => 'FAR']);
    $source = Subject::factory()->for($program)->create(['name' => 'FINANCIAL ACCOUNTING AND REPORTING', 'code' => 'FAR2']);
    $extraction = CurriculumExtraction::factory()->for($program)->for($file)->create([
        'source_hash' => $file->content_hash,
        'source_files' => curriculumSourceSnapshot($file),
        'proposal' => curriculumReviewData([], $file->id),
    ]);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('curriculum-extractions.publish', $extraction), ['subjects' => [[
            'include' => true, 'name' => 'Financial Accounting and Reporting', 'code' => 'FAR',
            'merge_target_id' => $target->id, 'merge_source_ids' => [$source->id], 'topics' => [],
        ]]])
        ->assertSessionHasErrors(['subjects' => 'The selected subjects have conflicting codes. Update the proposed code or choose compatible records.']);

    $this->assertModelExists($target);
    $this->assertModelExists($source);
    expect($extraction->fresh()->status)->toBe(CurriculumExtractionStatus::Reviewing)
        ->and(Storage::disk('local')->files('curriculum-merges'))->toBeEmpty();
});

test('publishing rolls back the entire merge when selected topic codes conflict', function () {
    Storage::fake('local');
    $program = Program::factory()->create();
    $file = ProgramFile::factory()->for($program)->create([
        'content_hash' => str_repeat('3', 64), 'embedding_status' => FileEmbeddingStatus::Complete,
    ]);
    $target = Subject::factory()->for($program)->create(['name' => 'Biology', 'code' => null]);
    $source = Subject::factory()->for($program)->create(['name' => 'BIOLOGY', 'code' => null]);
    $targetTopic = SyllabusTopic::factory()->for($target)->create(['title' => 'Cells', 'code' => 'CELL', 'parent_id' => null]);
    $sourceTopic = SyllabusTopic::factory()->for($source)->create(['title' => 'CELLS', 'code' => 'CELL2', 'parent_id' => null]);
    $extraction = CurriculumExtraction::factory()->for($program)->for($file)->create([
        'source_hash' => $file->content_hash,
        'source_files' => curriculumSourceSnapshot($file),
        'proposal' => curriculumReviewData([], $file->id),
    ]);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('curriculum-extractions.publish', $extraction), ['subjects' => [[
            'include' => true, 'name' => 'Biology', 'code' => null,
            'merge_target_id' => $target->id, 'merge_source_ids' => [$source->id], 'topics' => [],
        ]]])
        ->assertSessionHasErrors(['subjects' => 'The selected merge has a topic code or hierarchy conflict for "CELLS".']);

    $this->assertModelExists($target);
    $this->assertModelExists($source);
    $this->assertModelExists($targetTopic);
    $this->assertModelExists($sourceTopic);
    expect($extraction->fresh()->status)->toBe(CurriculumExtractionStatus::Reviewing)
        ->and(Storage::disk('local')->files('curriculum-merges'))->toBeEmpty();
});

test('publishing rejects a duplicate merge selection from another program', function () {
    $program = Program::factory()->create();
    $otherProgram = Program::factory()->create();
    $file = ProgramFile::factory()->for($program)->create([
        'content_hash' => str_repeat('f', 64), 'embedding_status' => FileEmbeddingStatus::Complete,
    ]);
    $target = Subject::factory()->for($program)->create(['name' => 'Biology', 'code' => null]);
    $foreignSubject = Subject::factory()->for($otherProgram)->create(['name' => 'Biology', 'code' => null]);
    $extraction = CurriculumExtraction::factory()->for($program)->for($file)->create([
        'source_hash' => $file->content_hash,
        'source_files' => curriculumSourceSnapshot($file),
        'proposal' => curriculumReviewData([], $file->id),
    ]);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('curriculum-extractions.publish', $extraction), ['subjects' => [[
            'include' => true, 'name' => 'Biology', 'code' => null,
            'merge_target_id' => $target->id, 'merge_source_ids' => [$foreignSubject->id], 'topics' => [],
        ]]])
        ->assertSessionHasErrors(['subjects' => 'The merge preview is stale. Refresh and review the matching subjects again.']);

    $this->assertModelExists($foreignSubject);
    $this->assertModelExists($target);
    expect($extraction->fresh()->status)->toBe(CurriculumExtractionStatus::Reviewing);
});

test('publishing keeps matching names scoped to their program subject and parent', function () {
    $otherSubject = Subject::factory()->create(['name' => 'BIOLOGY', 'code' => null]);
    SyllabusTopic::factory()->for($otherSubject)->create(['title' => 'CELLS', 'code' => null, 'parent_id' => null]);
    $program = Program::factory()->create();
    $file = ProgramFile::factory()->for($program)->create(['embedding_status' => FileEmbeddingStatus::Complete]);
    $extraction = CurriculumExtraction::factory()->for($program)->for($file)->create([
        'source_files' => curriculumSourceSnapshot($file),
        'proposal' => curriculumReviewData([
            ['title' => 'Cells', 'source_page' => null, 'parent_index' => null],
            ['title' => 'CELLS', 'source_page' => null, 'parent_index' => 0],
        ], $file->id),
    ]);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('curriculum-extractions.publish', $extraction), ['subjects' => [[
            'include' => true, 'name' => 'Biology', 'code' => null, 'topics' => [
                ['include' => true, 'title' => 'Cells', 'code' => null],
                ['include' => true, 'title' => 'CELLS', 'code' => null],
            ],
        ]]])
        ->assertSessionHasNoErrors();

    $subject = $program->subjects()->sole();
    $parent = $subject->syllabusTopics()->whereNull('parent_id')->sole();
    $this->assertDatabaseCount('subjects', 2);
    $this->assertDatabaseCount('syllabus_topics', 3);
    $this->assertDatabaseHas('syllabus_topics', ['subject_id' => $subject->id, 'parent_id' => $parent->id, 'title' => 'CELLS']);
});

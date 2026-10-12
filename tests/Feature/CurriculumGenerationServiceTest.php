<?php

use App\Ai\Agents\CurriculumProposalGenerator;
use App\CurriculumGenerationKind;
use App\CurriculumGenerationStatus;
use App\Jobs\GenerateCurriculumProposal;
use App\Models\Concept;
use App\Models\CurriculumGeneration;
use App\Models\Program;
use App\Models\ProgramFile;
use App\Models\Subject;
use App\Models\SyllabusTopic;
use App\Models\User;
use App\Services\CurriculumGenerationService;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    config([
        'ai.curriculum_generation.enabled' => true,
        'ai.curriculum_generation.provider' => 'openai',
        'ai.curriculum_generation.model' => 'test-model',
        'ai.curriculum_generation.models' => ['test-model'],
    ]);
});

test('curriculum proposal requests snapshot only eligible shared and subject files', function () {
    Queue::fake([GenerateCurriculumProposal::class]);
    $program = Program::factory()->create();
    $subject = Subject::factory()->for($program)->create();
    $sharedFile = ProgramFile::factory()->for($program)->create();
    $subjectFile = ProgramFile::factory()->for($program)->create(['subject_id' => $subject->id]);

    $generation = app(CurriculumGenerationService::class)->queue(
        $program,
        CurriculumGenerationKind::Topics,
        [$sharedFile->id, $subjectFile->id],
        User::factory()->admin()->create(),
        subject: $subject,
        requestContext: 'Prefer the established terminology.',
    );

    expect($generation->status)->toBe(CurriculumGenerationStatus::Queued)
        ->and($generation->source_files)->toHaveCount(2)
        ->and($generation->source_files[0]['id'])->toBe($sharedFile->id)
        ->and($generation->source_files[1]['subject_id'])->toBe($subject->id);
    Queue::assertPushed(GenerateCurriculumProposal::class, fn (GenerateCurriculumProposal $job): bool => $job->generationId === $generation->id);
});

test('curriculum proposal requests reject files outside the selected subject scope', function () {
    Queue::fake([GenerateCurriculumProposal::class]);
    $program = Program::factory()->create();
    $subject = Subject::factory()->for($program)->create();
    $otherSubject = Subject::factory()->for($program)->create();
    $file = ProgramFile::factory()->for($program)->create(['subject_id' => $otherSubject->id]);

    expect(fn () => app(CurriculumGenerationService::class)->queue(
        $program,
        CurriculumGenerationKind::Topics,
        [$file->id],
        User::factory()->admin()->create(),
        subject: $subject,
    ))->toThrow(ValidationException::class);

    expect(CurriculumGeneration::query()->count())->toBe(0);
    Queue::assertNothingPushed();
});

test('curriculum proposals remain private and accept optional citations in explicit ungrounded mode', function () {
    Queue::fake([GenerateCurriculumProposal::class]);
    CurriculumProposalGenerator::fake([[
        'items' => [[
            'draft_id' => 'subject-one',
            'name' => 'Accounting',
            'code' => 'ACCT',
            'description' => 'A proposed subject.',
            'parent_draft_id' => null,
            'citations' => [],
        ]],
    ]]);
    Storage::fake('s3');
    $program = Program::factory()->create();
    $file = ProgramFile::factory()->for($program)->create([
        'storage_key' => 'program-files/'.$program->id.'/source.pdf',
    ]);
    Storage::disk('s3')->put($file->storage_key, 'A source chosen for an ungrounded proposal.');
    $file->forceFill(['file_size' => Storage::disk('s3')->size($file->storage_key)])->save();
    $generation = app(CurriculumGenerationService::class)->queue(
        $program,
        CurriculumGenerationKind::Subjects,
        [$file->id],
        User::factory()->admin()->create(),
        requestContext: 'Draft a concise accounting subject.',
        ungrounded: true,
    );

    app(CurriculumGenerationService::class)->generate($generation->id);

    expect($generation->fresh()->status)->toBe(CurriculumGenerationStatus::Reviewing)
        ->and($generation->fresh()->proposal['items'][0]['citation_status'])->toBe('unverified')
        ->and($generation->fresh()->ungrounded_requested)->toBeTrue()
        ->and($generation->fresh()->source_files[0]['content_hash'])->toBe(hash('sha256', 'A source chosen for an ungrounded proposal.'));
    expect($program->subjects()->count())->toBe(0);
    expect(fn () => app(CurriculumGenerationService::class)->queue(
        $program,
        CurriculumGenerationKind::Subjects,
        [$file->id],
        $generation->requester()->firstOrFail(),
        requestContext: 'Draft a concise accounting subject.',
        ungrounded: true,
    ))->toThrow(ValidationException::class);
    CurriculumProposalGenerator::assertPrompted(fn ($prompt): bool => $prompt->contains('Draft a concise accounting subject.'));
});

test('curriculum proposal text extraction does not require vector embeddings', function () {
    Queue::fake([GenerateCurriculumProposal::class]);
    config(['ai.file_embeddings.enabled' => false]);
    Storage::fake('s3');
    Process::fake([
        '*' => Process::sequence()
            ->push(Process::result("Pages:          1\n"))
            ->push(Process::result("Assets appear on the balance sheet.\f"))
            ->push(Process::result("Pages:          1\n"))
            ->push(Process::result("Current and noncurrent assets are distinct classifications.\f")),
    ]);

    $program = Program::factory()->create();
    $subject = Subject::factory()->for($program)->create();
    $file = ProgramFile::factory()->for($program)->create([
        'storage_key' => 'program-files/'.$program->id.'/source.pdf',
        'content_hash' => null,
    ]);
    Storage::disk('s3')->put($file->storage_key, '%PDF-1.7 test document');
    $file->forceFill(['file_size' => Storage::disk('s3')->size($file->storage_key)])->save();
    $subjectFile = ProgramFile::factory()->for($program)->create([
        'subject_id' => $subject->id,
        'storage_key' => 'program-files/'.$program->id.'/subject-source.pdf',
        'content_hash' => null,
    ]);
    Storage::disk('s3')->put($subjectFile->storage_key, '%PDF-1.7 subject test document');
    $subjectFile->forceFill(['file_size' => Storage::disk('s3')->size($subjectFile->storage_key)])->save();
    $generation = app(CurriculumGenerationService::class)->queue(
        $program,
        CurriculumGenerationKind::Topics,
        [$file->id, $subjectFile->id],
        User::factory()->admin()->create(),
        subject: $subject,
    );
    CurriculumProposalGenerator::fake([[
        'items' => [[
            'draft_id' => 'topic-one',
            'title' => 'Financial statements',
            'code' => 'FINSTAT',
            'description' => null,
            'parent_draft_id' => null,
            'citations' => [
                ['source_file_id' => $file->id, 'page_number' => 1],
                ['source_file_id' => $subjectFile->id, 'page_number' => 1],
            ],
        ]],
    ]]);

    app(CurriculumGenerationService::class)->generate($generation->id);

    expect($generation->fresh()->status)->toBe(CurriculumGenerationStatus::Reviewing)
        ->and($generation->fresh()->source_files[0]['content_hash'])->toBe(hash('sha256', '%PDF-1.7 test document'))
        ->and($generation->fresh()->source_files[1]['subject_id'])->toBe($subject->id)
        ->and($generation->fresh()->proposal['items'][0]['citation_status'])->toBe('valid')
        ->and($generation->fresh()->proposal['items'][0]['citations'][0]['excerpt'])->toBe('Assets appear on the balance sheet.')
        ->and($generation->fresh()->proposal['items'][0]['citations'][1]['excerpt'])->toBe('Current and noncurrent assets are distinct classifications.');
    Process::assertRan(fn ($process): bool => in_array('pdftotext', $process->command, true));
    CurriculumProposalGenerator::assertPrompted(fn ($prompt): bool => $prompt->contains('Assets appear on the balance sheet.') && $prompt->contains('Current and noncurrent assets are distinct classifications.'));

    $proposalHash = hash('sha256', json_encode($generation->fresh()->proposal, JSON_THROW_ON_ERROR));
    app(CurriculumGenerationService::class)->review($generation->fresh(), $proposalHash, [[
        'draft_id' => 'topic-one',
        'include' => true,
        'title' => 'Reviewed financial statements',
        'code' => 'FINSTAT-REVIEWED',
        'description' => 'Author-edited topic description.',
        'sort_order' => 3,
    ]], User::factory()->admin()->create());

    $approvedTopic = SyllabusTopic::query()->where('subject_id', $subject->id)->where('code', 'FINSTAT-REVIEWED')->firstOrFail();
    expect($approvedTopic->title)->toBe('Reviewed financial statements')
        ->and($approvedTopic->sources()->count())->toBe(2)
        ->and($generation->fresh()->status)->toBe(CurriculumGenerationStatus::Approved);
});

test('grounded subject proposals use explicitly selected shared program files before approval', function () {
    Queue::fake([GenerateCurriculumProposal::class]);
    Storage::fake('s3');
    Process::fake([
        '*' => Process::sequence()
            ->push(Process::result("Pages:          1\n"))
            ->push(Process::result("The program includes financial accounting and taxation.\f")),
    ]);
    $program = Program::factory()->create();
    $file = ProgramFile::factory()->for($program)->create([
        'subject_id' => null,
        'storage_key' => 'program-files/'.$program->id.'/shared.pdf',
        'content_hash' => null,
    ]);
    Storage::disk('s3')->put($file->storage_key, '%PDF-1.7 shared program source');
    $file->forceFill(['file_size' => Storage::disk('s3')->size($file->storage_key)])->save();
    CurriculumProposalGenerator::fake([[
        'items' => [[
            'draft_id' => 'accounting-subject',
            'name' => 'Financial Accounting',
            'code' => 'FINACC',
            'description' => null,
            'parent_draft_id' => null,
            'citations' => [],
        ]],
    ]]);
    $generation = app(CurriculumGenerationService::class)->queue(
        $program,
        CurriculumGenerationKind::Subjects,
        [$file->id],
        User::factory()->admin()->create(),
    );

    app(CurriculumGenerationService::class)->generate($generation->id);

    expect($generation->fresh()->status)->toBe(CurriculumGenerationStatus::Reviewing)
        ->and($generation->fresh()->source_files[0]['subject_id'])->toBeNull()
        ->and($generation->fresh()->proposal['items'][0]['citation_status'])->toBe('unverified')
        ->and($program->subjects()->exists())->toBeFalse();
    CurriculumProposalGenerator::assertPrompted(fn ($prompt): bool => $prompt->contains('The program includes financial accounting and taxation.'));

    app(CurriculumGenerationService::class)->review($generation->fresh(), hash('sha256', json_encode($generation->fresh()->proposal, JSON_THROW_ON_ERROR)), [[
        'draft_id' => 'accounting-subject',
        'include' => true,
        'name' => 'Author-reviewed Financial Accounting',
        'code' => 'FINACC-REVIEWED',
        'description' => 'Edited against the program source.',
        'sort_order' => 2,
    ]], User::factory()->admin()->create());

    expect($program->subjects()->where('code', 'FINACC-REVIEWED')->value('name'))->toBe('Author-reviewed Financial Accounting')
        ->and($generation->fresh()->status)->toBe(CurriculumGenerationStatus::Approved);
});

test('transient provider failures return the proposal to the queue retry lifecycle', function () {
    Queue::fake([GenerateCurriculumProposal::class]);
    Storage::fake('s3');
    Process::fake([
        '*' => Process::sequence()
            ->push(Process::result("Pages:          1\n"))
            ->push(Process::result("A source page for curriculum generation.\f")),
    ]);
    $program = Program::factory()->create();
    $file = ProgramFile::factory()->for($program)->create(['storage_key' => 'program-files/'.$program->id.'/retry.pdf']);
    Storage::disk('s3')->put($file->storage_key, '%PDF-1.7 retry fixture');
    $file->forceFill(['file_size' => Storage::disk('s3')->size($file->storage_key)])->save();
    $generation = app(CurriculumGenerationService::class)->queue(
        $program,
        CurriculumGenerationKind::Subjects,
        [$file->id],
        User::factory()->admin()->create(),
    );
    CurriculumProposalGenerator::fake(fn () => throw new RuntimeException('Provider is temporarily unavailable.'));

    expect(fn () => app(CurriculumGenerationService::class)->generate($generation->id))
        ->toThrow(RuntimeException::class, 'Provider is temporarily unavailable.');

    expect($generation->fresh()->status)->toBe(CurriculumGenerationStatus::Queued)
        ->and($generation->fresh()->error_code)->toBeNull();
});

test('curriculum generation requires explicit ungrounded mode when selected PDFs have no readable text', function () {
    Queue::fake([GenerateCurriculumProposal::class]);
    CurriculumProposalGenerator::fake([]);
    Storage::fake('s3');
    Process::fake([
        '*' => Process::sequence()
            ->push(Process::result("Pages:          1\n"))
            ->push(Process::result("\f")),
    ]);
    $program = Program::factory()->create();
    $file = ProgramFile::factory()->for($program)->create([
        'storage_key' => 'program-files/'.$program->id.'/empty-text.pdf',
        'content_hash' => null,
    ]);
    Storage::disk('s3')->put($file->storage_key, '%PDF-1.7 empty text');
    $file->forceFill(['file_size' => Storage::disk('s3')->size($file->storage_key)])->save();
    $generation = app(CurriculumGenerationService::class)->queue(
        $program,
        CurriculumGenerationKind::Subjects,
        [$file->id],
        User::factory()->admin()->create(),
    );

    app(CurriculumGenerationService::class)->generate($generation->id);

    expect($generation->fresh()->status)->toBe(CurriculumGenerationStatus::Failed)
        ->and($generation->fresh()->error_code)->toBe('source_text_unavailable');
    CurriculumProposalGenerator::assertNeverPrompted();
});

test('a conflicting subject proposal does not block approval of independent items', function () {
    $program = Program::factory()->create();
    Subject::factory()->for($program)->create(['code' => 'DUP']);
    $generation = CurriculumGeneration::factory()->for($program)->create([
        'kind' => CurriculumGenerationKind::Subjects,
        'status' => CurriculumGenerationStatus::Reviewing,
        'proposal' => ['items' => [
            ['draft_id' => 'subject-duplicate', 'name' => 'Existing subject', 'code' => 'DUP', 'description' => null, 'parent_draft_id' => null, 'sort_order' => 0, 'citations' => [], 'citation_status' => 'unverified', 'review_notes' => []],
            ['draft_id' => 'subject-new', 'name' => 'New subject', 'code' => 'NEW', 'description' => null, 'parent_draft_id' => null, 'sort_order' => 1, 'citations' => [], 'citation_status' => 'unverified', 'review_notes' => []],
        ]],
    ]);
    $proposalHash = hash('sha256', json_encode($generation->proposal, JSON_THROW_ON_ERROR));
    $service = app(CurriculumGenerationService::class);
    $reviewer = User::factory()->admin()->create();

    $service->review($generation, $proposalHash, [
        ['draft_id' => 'subject-duplicate', 'include' => true, 'name' => 'Existing subject', 'code' => 'DUP', 'description' => '', 'sort_order' => 0],
        ['draft_id' => 'subject-new', 'include' => true, 'name' => 'New subject', 'code' => 'NEW', 'description' => '', 'sort_order' => 1],
    ], $reviewer);

    expect($generation->fresh()->status)->toBe(CurriculumGenerationStatus::Reviewing)
        ->and($generation->fresh()->reviewed_proposal['items'][0]['decision'])->toBe('conflict')
        ->and($generation->fresh()->reviewed_proposal['items'][1]['decision'])->toBe('approved');
    expect($program->subjects()->where('code', 'NEW')->exists())->toBeTrue();

    $service->review($generation->fresh(), $proposalHash, [
        ['draft_id' => 'subject-duplicate', 'include' => true, 'name' => 'Revised subject', 'code' => 'DUP-2', 'description' => '', 'sort_order' => 0],
        ['draft_id' => 'subject-new', 'include' => true, 'name' => 'New subject', 'code' => 'NEW', 'description' => '', 'sort_order' => 1],
    ], $reviewer);

    expect($generation->fresh()->status)->toBe(CurriculumGenerationStatus::Approved)
        ->and($generation->fresh()->reviewed_proposal['items'][0]['decision'])->toBe('approved')
        ->and($program->subjects()->where('code', 'DUP-2')->exists())->toBeTrue()
        ->and($program->subjects()->where('code', 'NEW')->count())->toBe(1);
});

test('topic proposals preserve reviewed hierarchy when approved', function () {
    $program = Program::factory()->create();
    $subject = Subject::factory()->for($program)->create();
    $generation = CurriculumGeneration::factory()->for($program)->for($subject)->create([
        'kind' => CurriculumGenerationKind::Topics,
        'status' => CurriculumGenerationStatus::Reviewing,
        'proposal' => ['items' => [
            ['draft_id' => 'topic-parent', 'title' => 'Financial reporting', 'code' => 'FR', 'description' => null, 'parent_draft_id' => null, 'sort_order' => 0, 'citations' => [], 'citation_status' => 'unverified', 'review_notes' => []],
            ['draft_id' => 'topic-child', 'title' => 'Balance sheets', 'code' => 'BS', 'description' => 'Assets and liabilities.', 'parent_draft_id' => 'topic-parent', 'sort_order' => 1, 'citations' => [], 'citation_status' => 'unverified', 'review_notes' => []],
        ]],
    ]);
    $proposalHash = hash('sha256', json_encode($generation->proposal, JSON_THROW_ON_ERROR));

    app(CurriculumGenerationService::class)->review($generation, $proposalHash, [
        ['draft_id' => 'topic-parent', 'include' => true, 'title' => 'Financial reporting', 'code' => 'FR', 'description' => '', 'sort_order' => 0],
        ['draft_id' => 'topic-child', 'include' => true, 'title' => 'Balance sheets', 'code' => 'BS', 'description' => 'Assets and liabilities.', 'parent_draft_id' => 'topic-parent', 'sort_order' => 1],
    ], User::factory()->admin()->create());

    $parent = SyllabusTopic::query()->where('subject_id', $subject->id)->where('code', 'FR')->firstOrFail();
    $child = SyllabusTopic::query()->where('subject_id', $subject->id)->where('code', 'BS')->firstOrFail();
    expect($child->parent_id)->toBe($parent->id)
        ->and($generation->fresh()->status)->toBe(CurriculumGenerationStatus::Approved);
});

test('subject-wide concept proposals use the complete topic tree and keep item decisions independent', function () {
    Queue::fake([GenerateCurriculumProposal::class]);
    $program = Program::factory()->create();
    $subject = Subject::factory()->for($program)->create();
    $rootTopic = SyllabusTopic::factory()->for($subject)->create([
        'code' => 'ROOT',
        'title' => 'Root topic',
        'description' => 'Root description.',
        'parent_id' => null,
        'sort_order' => 0,
    ]);
    $nestedTopic = SyllabusTopic::factory()->for($subject)->create([
        'code' => 'NESTED',
        'title' => 'Nested topic',
        'parent_id' => $rootTopic->id,
        'sort_order' => 0,
    ]);
    $deepTopic = SyllabusTopic::factory()->for($subject)->create([
        'code' => 'DEEP',
        'title' => 'Deeply nested topic',
        'parent_id' => $nestedTopic->id,
        'sort_order' => 0,
    ]);
    $siblingTopic = SyllabusTopic::factory()->for($subject)->create([
        'code' => 'SIBLING',
        'title' => 'Sibling topic',
        'parent_id' => null,
        'sort_order' => 1,
    ]);
    $otherSubject = Subject::factory()->for($program)->create();
    $foreignTopic = SyllabusTopic::factory()->for($otherSubject)->create(['title' => 'Out of scope topic']);
    $existingConcept = Concept::factory()->for($subject)->for($rootTopic, 'syllabusTopic')->create([
        'code' => 'EXISTING',
        'title' => 'Existing anchor concept',
    ]);
    CurriculumProposalGenerator::fake([[
        'items' => [
            ['draft_id' => 'concept-mapped', 'title' => 'Mapped concept', 'code' => 'APP-01', 'description' => 'A teachable idea.', 'parent_draft_id' => null, 'syllabus_topic_id' => $deepTopic->id],
            ['draft_id' => 'concept-invalid-topic', 'title' => 'Unmapped concept', 'code' => 'APP-02', 'description' => null, 'parent_draft_id' => null, 'syllabus_topic_id' => $foreignTopic->id],
            ['draft_id' => 'concept-duplicate', 'title' => 'Existing anchor concept', 'code' => 'EXISTING', 'description' => null, 'parent_draft_id' => null, 'syllabus_topic_id' => $rootTopic->id],
            ['draft_id' => 'concept-independent', 'title' => 'Independent concept', 'code' => 'APP-03', 'description' => null, 'parent_draft_id' => null, 'syllabus_topic_id' => $siblingTopic->id],
        ],
    ]]);

    $generation = app(CurriculumGenerationService::class)->queue(
        $program,
        CurriculumGenerationKind::Concepts,
        [],
        User::factory()->admin()->create(),
        subject: $subject,
        requestContext: 'Create concise instructional concepts.',
    );

    expect($generation->scope_snapshot['topics'])->toHaveCount(4)
        ->and($generation->scope_snapshot['topics'][1]['path'][0]['id'])->toBe($rootTopic->id)
        ->and($generation->scope_snapshot['topics'][2]['path'][2]['id'])->toBe($deepTopic->id)
        ->and($generation->source_files)->toBe([])
        ->and($generation->ungrounded_requested)->toBeTrue();
    Queue::assertPushed(GenerateCurriculumProposal::class, fn (GenerateCurriculumProposal $job): bool => $job->generationId === $generation->id);

    $service = app(CurriculumGenerationService::class);
    $service->generate($generation->id);

    expect($generation->fresh()->status)->toBe(CurriculumGenerationStatus::Reviewing)
        ->and($generation->fresh()->source_files)->toBe([])
        ->and($generation->fresh()->proposal['items'][0]['syllabus_topic_id'])->toBe($deepTopic->id)
        ->and($generation->fresh()->proposal['items'][1]['syllabus_topic_id'])->toBeNull()
        ->and($generation->fresh()->proposal['items'][1]['review_notes'])->not->toBeEmpty()
        ->and(Concept::query()->where('subject_id', $subject->id)->count())->toBe(1);
    CurriculumProposalGenerator::assertPrompted(fn ($prompt): bool => $prompt->contains('Root topic')
        && $prompt->contains('Nested topic')
        && $prompt->contains('Deeply nested topic')
        && $prompt->contains('Sibling topic')
        && $prompt->contains('Existing anchor concept'));

    $proposalHash = hash('sha256', json_encode($generation->fresh()->proposal, JSON_THROW_ON_ERROR));
    $reviewer = User::factory()->admin()->create();
    $service->review($generation->fresh(), $proposalHash, [
        ['draft_id' => 'concept-mapped', 'include' => true, 'title' => 'Mapped concept', 'code' => 'APP-01', 'description' => 'Edited description.', 'sort_order' => 0, 'syllabus_topic_id' => $siblingTopic->id],
        ['draft_id' => 'concept-invalid-topic', 'include' => false, 'title' => 'Unmapped concept', 'code' => 'APP-02', 'description' => '', 'sort_order' => 1],
        ['draft_id' => 'concept-duplicate', 'include' => true, 'title' => 'Existing anchor concept', 'code' => 'EXISTING', 'description' => '', 'sort_order' => 2, 'syllabus_topic_id' => $rootTopic->id],
        ['draft_id' => 'concept-independent', 'include' => true, 'title' => 'Independent concept', 'code' => 'APP-03', 'description' => '', 'sort_order' => 3, 'syllabus_topic_id' => $siblingTopic->id],
    ], $reviewer);

    $mappedConcept = Concept::query()->where('subject_id', $subject->id)->where('code', 'APP-01')->firstOrFail();
    expect($mappedConcept->syllabus_topic_id)->toBe($siblingTopic->id)
        ->and(Concept::query()->where('subject_id', $subject->id)->where('code', 'APP-02')->exists())->toBeFalse()
        ->and(Concept::query()->where('subject_id', $subject->id)->where('code', 'APP-03')->exists())->toBeTrue()
        ->and($generation->fresh()->status)->toBe(CurriculumGenerationStatus::Reviewing)
        ->and($generation->fresh()->reviewed_proposal['items'][1]['decision'])->toBe('rejected')
        ->and($generation->fresh()->reviewed_proposal['items'][1]['review_notes'])->not->toBeEmpty()
        ->and($generation->fresh()->reviewed_proposal['items'][2]['decision'])->toBe('conflict')
        ->and($generation->fresh()->reviewed_proposal['items'][3]['decision'])->toBe('approved');

    $service->review($generation->fresh(), $proposalHash, [
        ['draft_id' => 'concept-mapped', 'include' => true, 'title' => 'Mapped concept', 'code' => 'APP-01', 'description' => 'Edited description.', 'sort_order' => 0, 'syllabus_topic_id' => $siblingTopic->id],
        ['draft_id' => 'concept-invalid-topic', 'include' => false, 'title' => 'Unmapped concept', 'code' => 'APP-02', 'description' => '', 'sort_order' => 1],
        ['draft_id' => 'concept-duplicate', 'include' => true, 'title' => 'Resolved concept', 'code' => 'APP-04', 'description' => '', 'sort_order' => 2, 'syllabus_topic_id' => $rootTopic->id],
        ['draft_id' => 'concept-independent', 'include' => true, 'title' => 'Independent concept', 'code' => 'APP-03', 'description' => '', 'sort_order' => 3, 'syllabus_topic_id' => $siblingTopic->id],
    ], $reviewer);

    expect($generation->fresh()->status)->toBe(CurriculumGenerationStatus::Approved)
        ->and(Concept::query()->where('subject_id', $subject->id)->where('code', 'APP-04')->value('syllabus_topic_id'))->toBe($rootTopic->id)
        ->and(Concept::query()->where('subject_id', $subject->id)->count())->toBe(4);
});

test('concept proposals become stale when the subject topic tree changes before review', function () {
    Queue::fake([GenerateCurriculumProposal::class]);
    $program = Program::factory()->create();
    $subject = Subject::factory()->for($program)->create();
    $topic = SyllabusTopic::factory()->for($subject)->create(['title' => 'Original topic']);
    CurriculumProposalGenerator::fake([[
        'items' => [[
            'draft_id' => 'concept-stale',
            'title' => 'Stale concept',
            'code' => 'APP-STALE',
            'description' => null,
            'parent_draft_id' => null,
            'syllabus_topic_id' => $topic->id,
        ]],
    ]]);
    $generation = app(CurriculumGenerationService::class)->queue(
        $program,
        CurriculumGenerationKind::Concepts,
        [],
        User::factory()->admin()->create(),
        subject: $subject,
    );
    $service = app(CurriculumGenerationService::class);
    $service->generate($generation->id);
    $originalRequestHash = $generation->request_hash;
    $topic->update(['title' => 'Changed topic']);
    $regeneratedRequest = $service->queue(
        $program,
        CurriculumGenerationKind::Concepts,
        [],
        $generation->requester()->firstOrFail(),
        subject: $subject,
    );

    expect($regeneratedRequest->request_hash)->not->toBe($originalRequestHash);

    $service->review($generation->fresh(), hash('sha256', json_encode($generation->fresh()->proposal, JSON_THROW_ON_ERROR)), [[
        'draft_id' => 'concept-stale',
        'include' => true,
        'title' => 'Stale concept',
        'code' => 'APP-STALE',
        'description' => '',
        'sort_order' => 0,
        'syllabus_topic_id' => $topic->id,
    ]], User::factory()->admin()->create());

    expect($generation->fresh()->status)->toBe(CurriculumGenerationStatus::Stale)
        ->and($generation->fresh()->error_code)->toBe('scope_changed')
        ->and(Concept::query()->where('subject_id', $subject->id)->exists())->toBeFalse();
});

test('concept requests reject a topic tree that exceeds configured input bounds', function () {
    Queue::fake([GenerateCurriculumProposal::class]);
    config(['ai.curriculum_generation.max_topics' => 1]);
    $program = Program::factory()->create();
    $subject = Subject::factory()->for($program)->create();
    SyllabusTopic::factory()->for($subject)->count(2)->create();

    expect(fn () => app(CurriculumGenerationService::class)->queue(
        $program,
        CurriculumGenerationKind::Concepts,
        [],
        User::factory()->admin()->create(),
        subject: $subject,
    ))->toThrow(ValidationException::class);

    expect(CurriculumGeneration::query()->count())->toBe(0);
    Queue::assertNothingPushed();
});

test('concept requests reject a topic snapshot that exceeds the configured character limit', function () {
    Queue::fake([GenerateCurriculumProposal::class]);
    config(['ai.curriculum_generation.max_characters' => 32]);
    $program = Program::factory()->create();
    $subject = Subject::factory()->for($program)->create();
    SyllabusTopic::factory()->for($subject)->create(['title' => 'A detailed topic title that exceeds the limit']);

    expect(fn () => app(CurriculumGenerationService::class)->queue(
        $program,
        CurriculumGenerationKind::Concepts,
        [],
        User::factory()->admin()->create(),
        subject: $subject,
    ))->toThrow(ValidationException::class);

    expect(CurriculumGeneration::query()->count())->toBe(0);
    Queue::assertNothingPushed();
});

test('malformed subject-wide concept output fails without creating canonical concepts', function () {
    Queue::fake([GenerateCurriculumProposal::class]);
    $program = Program::factory()->create();
    $subject = Subject::factory()->for($program)->create();
    SyllabusTopic::factory()->for($subject)->create();
    CurriculumProposalGenerator::fake([['items' => []]]);
    $generation = app(CurriculumGenerationService::class)->queue(
        $program,
        CurriculumGenerationKind::Concepts,
        [],
        User::factory()->admin()->create(),
        subject: $subject,
    );

    app(CurriculumGenerationService::class)->generate($generation->id);

    expect($generation->fresh()->status)->toBe(CurriculumGenerationStatus::Failed)
        ->and($generation->fresh()->error_code)->toBe('invalid_proposal')
        ->and(Concept::query()->where('subject_id', $subject->id)->exists())->toBeFalse();
});

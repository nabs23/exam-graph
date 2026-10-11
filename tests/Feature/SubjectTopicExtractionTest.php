<?php

use App\Ai\Agents\SubjectTopicExtractor;
use App\FileEmbeddingStatus;
use App\FileUploadStatus;
use App\Jobs\ExtractSubjectTopics;
use App\Models\FilePageEmbedding;
use App\Models\Program;
use App\Models\Subject;
use App\Models\SubjectFile;
use App\Models\SubjectTopicExtraction;
use App\Models\SyllabusTopic;
use App\Models\User;
use App\Services\SubjectTopicExtractionService;
use App\SubjectTopicExtractionStatus;
use Illuminate\Support\Facades\Bus;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Ai\Ai;

beforeEach(function (): void {
    config([
        'ai.subject_topic_extraction.enabled' => true,
        'ai.subject_topic_extraction.provider' => 'openai',
        'ai.subject_topic_extraction.model' => 'gpt-4.1-mini',
        'ai.subject_topic_extraction.models' => ['gpt-4.1-mini'],
        'ai.subject_topic_extraction.timeout' => 60,
        'ai.providers.openai.key' => 'test-key',
    ]);
});

test('the subject page exposes a topic extraction tab and its nested extraction route lists owned files', function () {
    $program = Program::factory()->create();
    $subject = Subject::factory()->for($program)->create();
    $file = readySubjectTopicFile($subject, 'Source curriculum');
    addSubjectTopicPage($file, 1, '1.1 Financial reporting concepts.');
    $otherSubject = Subject::factory()->create();
    $otherFile = readySubjectTopicFile($otherSubject, 'Other subject source');
    addSubjectTopicPage($otherFile, 1, 'Unrelated source text.');
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('subjects.show', [$program, $subject]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/subjects/show')
            ->where('topicExtractionSummary.count', 0)
            ->where('topicExtractionEnabled', true));

    $this->actingAs($admin)
        ->get(route('programs.subjects.topics-extraction.index', [$program, $subject]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/subject-topic-extractions/index')
            ->where('subject.route_parameters.program', $program->id)
            ->where('subject.route_parameters.subject', $subject->id)
            ->has('files', 1)
            ->where('files.0.id', $file->id)
            ->where('files.0.eligible', true)
            ->where('files.0.usable_pages_count', 1)
            ->missing('files.0.storage_key'));
});

test('content managers can request an extraction from selected files owned by the route subject', function () {
    $program = Program::factory()->create();
    $subject = Subject::factory()->for($program)->create();
    $file = readySubjectTopicFile($subject);
    addSubjectTopicPage($file, 1, 'Official topic: Financial reporting.');
    $otherFile = readySubjectTopicFile(Subject::factory()->create());
    addSubjectTopicPage($otherFile, 1, 'Not owned by the selected subject.');
    $admin = User::factory()->admin()->create();
    Bus::fake([ExtractSubjectTopics::class]);

    $this->actingAs($admin)
        ->post(route('programs.subjects.topics-extraction.store', [$program, $subject]), [
            'source_file_ids' => [$file->id],
            'model' => 'gpt-4.1-mini',
        ])
        ->assertRedirect();

    $extraction = SubjectTopicExtraction::query()->sole();
    expect($extraction->subject_id)->toBe($subject->id)
        ->and($extraction->source_files)->toBe(subjectTopicSnapshot($file));
    Bus::assertDispatched(ExtractSubjectTopics::class, fn (ExtractSubjectTopics $job): bool => $job->extractionId === $extraction->id);

    $this->actingAs($admin)
        ->from(route('programs.subjects.topics-extraction.index', [$program, $subject]))
        ->post(route('programs.subjects.topics-extraction.store', [$program, $subject]), [
            'source_file_ids' => [$otherFile->id],
        ])
        ->assertSessionHasErrors('source_file_ids');

    expect(SubjectTopicExtraction::query()->count())->toBe(1);
});

test('the structured agent stores only topics with citations in the selected page snapshot', function () {
    $subject = Subject::factory()->create(['name' => 'Financial Accounting']);
    $file = readySubjectTopicFile($subject, 'Official syllabus');
    addSubjectTopicPage($file, 3, '4.2 Property, plant and equipment.');
    $extraction = SubjectTopicExtraction::factory()->for($subject)->create([
        'status' => SubjectTopicExtractionStatus::Queued,
        'source_files' => subjectTopicSnapshot($file),
        'source_hash' => hash('sha256', json_encode(subjectTopicSnapshot($file), JSON_THROW_ON_ERROR)),
        'proposal' => null,
    ]);
    Ai::fakeAgent(SubjectTopicExtractor::class, [[
        'topics' => [[
            'code' => '4.2',
            'title' => 'Property, plant and equipment',
            'description' => null,
            'parent_index' => null,
            'source_pages' => [['source_file_id' => $file->id, 'page_number' => 3]],
        ]],
    ]]);

    app(SubjectTopicExtractionService::class)->extract($extraction->id);

    $extraction->refresh();
    expect($extraction->status)->toBe(SubjectTopicExtractionStatus::Reviewing)
        ->and($extraction->proposal['topics'][0]['code'])->toBe('4.2')
        ->and($extraction->proposal['topics'][0]['source_pages'])->toBe([['source_file_id' => $file->id, 'page_number' => 3]]);
    Ai::assertAgentWasPrompted(SubjectTopicExtractor::class, fn ($prompt): bool => true);
});

test('a proposal with a citation outside the selected source pages never reaches review', function () {
    $subject = Subject::factory()->create();
    $file = readySubjectTopicFile($subject);
    addSubjectTopicPage($file, 1, '4.2 Property, plant and equipment.');
    $extraction = SubjectTopicExtraction::factory()->for($subject)->create([
        'status' => SubjectTopicExtractionStatus::Queued,
        'source_files' => subjectTopicSnapshot($file),
        'source_hash' => hash('sha256', json_encode(subjectTopicSnapshot($file), JSON_THROW_ON_ERROR)),
        'proposal' => null,
    ]);
    Ai::fakeAgent(SubjectTopicExtractor::class, [[
        'topics' => [[
            'code' => '4.2',
            'title' => 'Property, plant and equipment',
            'description' => null,
            'parent_index' => null,
            'source_pages' => [['source_file_id' => $file->id, 'page_number' => 99]],
        ]],
    ]]);

    app(SubjectTopicExtractionService::class)->extract($extraction->id);

    expect($extraction->fresh()->status)->toBe(SubjectTopicExtractionStatus::Failed)
        ->and($extraction->fresh()->error_code)->toBe('invalid_proposal')
        ->and($extraction->fresh()->proposal)->toBeNull();
});

test('publishing selected topics stores their source mappings atomically', function () {
    $subject = Subject::factory()->create();
    $firstFile = readySubjectTopicFile($subject, 'Official syllabus');
    $secondFile = readySubjectTopicFile($subject, 'Reviewer notes');
    addSubjectTopicPage($firstFile, 1, '4.2 Property, plant and equipment.');
    addSubjectTopicPage($firstFile, 2, '4.2.1 Recognition and measurement.');
    addSubjectTopicPage($secondFile, 5, 'Recognition is based on probable benefits.');
    $proposal = ['topics' => [
        [
            'code' => '4.2',
            'title' => 'Property, plant and equipment',
            'description' => null,
            'parent_index' => null,
            'source_pages' => [['source_file_id' => $firstFile->id, 'page_number' => 1]],
        ],
        [
            'code' => '4.2.1',
            'title' => 'Recognition and measurement',
            'description' => null,
            'parent_index' => 0,
            'source_pages' => [
                ['source_file_id' => $firstFile->id, 'page_number' => 2],
                ['source_file_id' => $secondFile->id, 'page_number' => 5],
            ],
        ],
    ]];
    $files = [subjectTopicSnapshot($firstFile)[0], subjectTopicSnapshot($secondFile)[0]];
    usort($files, fn (array $left, array $right): int => $left['id'] <=> $right['id']);
    $extraction = SubjectTopicExtraction::factory()->for($subject)->create([
        'source_files' => $files,
        'source_hash' => hash('sha256', json_encode($files, JSON_THROW_ON_ERROR)),
        'proposal' => $proposal,
    ]);
    $reviewer = User::factory()->admin()->create();

    app(SubjectTopicExtractionService::class)->publish($extraction, [
        ['include' => '1', 'code' => '4.2', 'title' => 'Property, plant and equipment', 'description' => '', 'parent_index' => '', 'sort_order' => '0'],
        ['include' => '1', 'code' => '4.2.1', 'title' => 'Recognition and measurement', 'description' => '', 'parent_index' => '0', 'sort_order' => '1'],
    ], $reviewer);

    $root = SyllabusTopic::query()->where('subject_id', $subject->id)->where('code', '4.2')->firstOrFail();
    $child = SyllabusTopic::query()->where('subject_id', $subject->id)->where('code', '4.2.1')->firstOrFail();
    expect($child->parent_id)->toBe($root->id)
        ->and($root->sources()->count())->toBe(1)
        ->and($child->sources()->count())->toBe(2)
        ->and($extraction->fresh()->status)->toBe(SubjectTopicExtractionStatus::Published)
        ->and($extraction->fresh()->reviewed_by)->toBe($reviewer->id);

    $firstFile->delete();
    $mapping = $root->sources()->sole();
    expect($mapping->subject_file_id)->toBeNull()
        ->and($mapping->source_file_id_snapshot)->toBe($firstFile->id)
        ->and($mapping->source_title)->toBe('Official syllabus')
        ->and($mapping->source_hash)->toBe($firstFile->content_hash)
        ->and($mapping->page_number)->toBe(1);
});

test('publishing is rejected and records source change when a selected file changes', function () {
    $subject = Subject::factory()->create();
    $file = readySubjectTopicFile($subject);
    addSubjectTopicPage($file, 1, '4.2 Property, plant and equipment.');
    $snapshot = subjectTopicSnapshot($file);
    $extraction = SubjectTopicExtraction::factory()->for($subject)->create([
        'source_files' => $snapshot,
        'source_hash' => hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR)),
        'proposal' => ['topics' => [[
            'code' => '4.2', 'title' => 'Property, plant and equipment', 'description' => null,
            'parent_index' => null, 'source_pages' => [['source_file_id' => $file->id, 'page_number' => 1]],
        ]]],
    ]);
    $file->forceFill(['content_hash' => str_repeat('f', 64)])->save();

    expect(fn () => app(SubjectTopicExtractionService::class)->publish($extraction, [
        ['include' => '1', 'code' => '4.2', 'title' => 'Property, plant and equipment', 'parent_index' => '', 'sort_order' => '0'],
    ], User::factory()->admin()->create()))->toThrow(ValidationException::class);

    expect($extraction->fresh()->status)->toBe(SubjectTopicExtractionStatus::Failed)
        ->and($extraction->fresh()->error_code)->toBe('source_changed')
        ->and($subject->syllabusTopics()->count())->toBe(0)
        ->and($subject->topicExtractions()->count())->toBe(1);
});

test('nested extraction routes do not expose another subjects proposal', function () {
    $program = Program::factory()->create();
    $subject = Subject::factory()->for($program)->create();
    $otherSubject = Subject::factory()->create();
    $extraction = SubjectTopicExtraction::factory()->for($otherSubject)->create();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('programs.subjects.topics-extraction.show', [$program, $subject, $extraction]))
        ->assertNotFound();
});

function readySubjectTopicFile(Subject $subject, ?string $title = null): SubjectFile
{
    return SubjectFile::factory()->for($subject)->create([
        'title' => $title ?? fake()->sentence(3),
        'content_hash' => hash('sha256', fake()->uuid()),
        'mime_type' => 'application/pdf',
        'upload_status' => FileUploadStatus::Uploaded,
        'embedding_status' => FileEmbeddingStatus::Complete,
    ]);
}

function addSubjectTopicPage(SubjectFile $file, int $pageNumber, string $content): FilePageEmbedding
{
    return FilePageEmbedding::query()->create([
        'subject_file_id' => $file->id,
        'source_key' => 'subject_file:'.$file->id,
        'page_number' => $pageNumber,
        'source_hash' => $file->content_hash,
        'provider' => 'voyageai',
        'model' => 'voyage-4',
        'dimensions' => 1024,
        'content' => $content,
        'embedding' => array_fill(0, 1024, 0.0),
        'embedded_at' => now(),
    ]);
}

/** @return array<int, array{id: int, content_hash: string, title: string, mime_type: string}> */
function subjectTopicSnapshot(SubjectFile $file): array
{
    return [[
        'id' => $file->id,
        'content_hash' => $file->content_hash,
        'title' => $file->title,
        'mime_type' => $file->mime_type,
    ]];
}

<?php

use App\Ai\Agents\OfficialCurriculumExtractor;
use App\CurriculumExtractionStatus;
use App\FileEmbeddingStatus;
use App\Models\CurriculumExtraction;
use App\Models\FilePageEmbedding;
use App\Models\Program;
use App\Models\ProgramFile;
use App\Models\User;
use App\Services\OfficialCurriculumExtractionService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Ai;

beforeEach(function (): void {
    if (DB::connection()->getDriverName() !== 'pgsql' || ! DB::table('pg_extension')->where('extname', 'vector')->exists()) {
        $this->markTestSkipped('PostgreSQL with pgvector is required for curriculum extraction integration tests.');
    }

    config([
        'ai.official_curriculum.enabled' => true,
        'ai.official_curriculum.provider' => 'openai',
        'ai.official_curriculum.model' => 'gpt-4.1-mini',
        'ai.providers.openai.key' => 'test-key',
    ]);
});

test('extraction saves only validated structured output with source page provenance', function () {
    $hash = str_repeat('c', 64);
    $program = Program::factory()->create();
    $file = ProgramFile::factory()->for($program)->create([
        'content_hash' => $hash,
        'embedding_status' => FileEmbeddingStatus::Complete,
    ]);
    FilePageEmbedding::query()->create([
        'program_file_id' => $file->id,
        'source_key' => 'program_file:'.$file->id,
        'page_number' => 4,
        'source_hash' => $hash,
        'provider' => 'voyageai',
        'model' => 'voyage-4',
        'dimensions' => 1024,
        'content' => 'Official subject: Biology. Topic: Cell structure.',
        'embedding' => array_fill(0, 1024, 0.0),
        'embedded_at' => now(),
    ]);
    $extraction = CurriculumExtraction::factory()->for($program)->for($file)->create([
        'requested_by' => User::factory()->admin()->create()->id,
        'source_hash' => $hash,
        'status' => CurriculumExtractionStatus::Processing,
        'source_files' => [['id' => $file->id, 'content_hash' => $hash, 'title' => $file->title]],
    ]);

    Ai::fakeAgent(OfficialCurriculumExtractor::class, [[
        'subjects' => [[
            'code' => null,
            'name' => 'Biology',
            'description' => 'Introduces the study of living systems.',
            'description_origin' => 'ai_generated',
            'source_file_id' => $file->id,
            'source_page' => 4,
            'topics' => [[
                'code' => null,
                'title' => 'Cell structure',
                'description' => 'Describes the parts and organization of a cell.',
                'description_origin' => 'ai_generated',
                'source_file_id' => $file->id,
                'source_page' => 4,
                'parent_index' => null,
            ]],
        ]],
    ]]);

    app(OfficialCurriculumExtractionService::class)->extract($extraction->id, isRetry: true);

    $extraction->refresh();
    expect($extraction->status)->toBe(CurriculumExtractionStatus::Reviewing)
        ->and($extraction->proposal['subjects'][0]['name'])->toBe('Biology')
        ->and($extraction->proposal['subjects'][0]['description_origin'])->toBe('ai_generated')
        ->and($extraction->proposal['subjects'][0]['topics'][0]['description'])->toBe('Describes the parts and organization of a cell.')
        ->and($extraction->proposal['subjects'][0]['topics'][0]['source_page'])->toBe(4)
        ->and($extraction->error_code)->toBeNull();
});

test('a duplicate first-attempt job cannot claim an extraction already processing', function () {
    $extraction = CurriculumExtraction::factory()->create(['status' => CurriculumExtractionStatus::Processing]);
    Ai::fakeAgent(OfficialCurriculumExtractor::class, []);

    app(OfficialCurriculumExtractionService::class)->extract($extraction->id);

    expect($extraction->fresh()->status)->toBe(CurriculumExtractionStatus::Processing);
    Ai::assertAgentNeverPrompted(OfficialCurriculumExtractor::class);
});

test('a changed extraction model can queue a new proposal for the same source snapshot', function () {
    Bus::fake();
    $hash = str_repeat('e', 64);
    $program = Program::factory()->create();
    $file = ProgramFile::factory()->for($program)->create([
        'content_hash' => $hash,
        'embedding_status' => FileEmbeddingStatus::Complete,
    ]);
    FilePageEmbedding::query()->create([
        'program_file_id' => $file->id,
        'source_key' => 'program_file:'.$file->id,
        'page_number' => 1,
        'source_hash' => $hash,
        'provider' => 'voyageai',
        'model' => 'voyage-4',
        'dimensions' => 1024,
        'content' => 'Official subject: Biology.',
        'embedding' => array_fill(0, 1024, 0.0),
        'embedded_at' => now(),
    ]);
    $snapshot = [['id' => $file->id, 'content_hash' => $hash, 'title' => $file->title]];
    $sourceHash = hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR));
    CurriculumExtraction::factory()->for($program)->create([
        'source_hash' => $sourceHash,
        'source_files' => $snapshot,
        'provider' => 'openai',
        'model' => 'gpt-4.1-mini',
        'status' => CurriculumExtractionStatus::Reviewing,
    ]);
    config(['ai.official_curriculum.model' => 'gpt-4.1']);

    $extraction = app(OfficialCurriculumExtractionService::class)->queue($program, User::factory()->admin()->create());

    expect($extraction->status)->toBe(CurriculumExtractionStatus::Queued)
        ->and($extraction->model)->toBe('gpt-4.1')
        ->and($extraction->source_hash)->toBe($sourceHash);
});

test('extraction allows review when the provider cites a page absent from the source', function () {
    $hash = str_repeat('d', 64);
    $program = Program::factory()->create();
    $file = ProgramFile::factory()->for($program)->create([
        'content_hash' => $hash,
        'embedding_status' => FileEmbeddingStatus::Complete,
    ]);
    FilePageEmbedding::query()->create([
        'program_file_id' => $file->id,
        'source_key' => 'program_file:'.$file->id,
        'page_number' => 1,
        'source_hash' => $hash,
        'provider' => 'voyageai',
        'model' => 'voyage-4',
        'dimensions' => 1024,
        'content' => 'Official subject: Biology.',
        'embedding' => array_fill(0, 1024, 0.0),
        'embedded_at' => now(),
    ]);
    $extraction = CurriculumExtraction::factory()->for($program)->for($file)->create([
        'source_hash' => $hash,
        'status' => CurriculumExtractionStatus::Queued,
        'source_files' => [['id' => $file->id, 'content_hash' => $hash, 'title' => $file->title]],
    ]);

    Ai::fakeAgent(OfficialCurriculumExtractor::class, [[
        'subjects' => [[
            'code' => null,
            'name' => 'Biology',
            'description' => null,
            'description_origin' => 'unavailable',
            'source_file_id' => $file->id,
            'source_page' => 99,
            'topics' => [],
        ]],
    ]]);

    app(OfficialCurriculumExtractionService::class)->extract($extraction->id);

    expect($extraction->fresh()->status)->toBe(CurriculumExtractionStatus::Reviewing)
        ->and($extraction->fresh()->error_code)->toBeNull()
        ->and($extraction->fresh()->proposal['subjects'][0]['source_file_id'])->toBeNull()
        ->and($extraction->fresh()->proposal['subjects'][0]['source_page'])->toBeNull();
});

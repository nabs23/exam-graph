<?php

use App\Exceptions\FileEmbeddingException;
use App\FileEmbeddingStatus;
use App\Jobs\EmbedSourceFile;
use App\Models\Program;
use App\Models\ProgramFile;
use App\Services\FileEmbeddingService;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Prompts\EmbeddingsPrompt;

beforeEach(function (): void {
    if (DB::connection()->getDriverName() !== 'pgsql') {
        $this->markTestSkipped('This test requires the dedicated PostgreSQL and pgvector test environment.');
    }

    config([
        'ai.file_embeddings.enabled' => true,
        'ai.providers.voyageai.key' => 'test-key',
        'ai.providers.voyageai.models.embeddings.default' => 'voyage-4',
        'ai.providers.voyageai.models.embeddings.dimensions' => 1024,
    ]);
});

test('persists extracted page text and vectors in pgvector', function () {
    Storage::fake('local');
    Embeddings::fake();
    Process::fake([
        '*' => Process::sequence()
            ->push(Process::result("Pages:          3\n"))
            ->push(Process::result("First page\fSecond page\f \f")),
    ]);

    $program = Program::factory()->create();
    $storageKey = 'program-files/'.$program->id.'/source.pdf';
    $pdf = '%PDF-1.4 test source';

    Storage::disk('local')->put($storageKey, $pdf);

    $file = ProgramFile::factory()->for($program)->create([
        'storage_disk' => 'local',
        'storage_key' => $storageKey,
        'file_size' => strlen($pdf),
        'mime_type' => 'application/pdf',
    ]);

    app(FileEmbeddingService::class)->embed($file);

    expect($file->fresh()->embedding_status)->toBe(FileEmbeddingStatus::Complete);

    $embeddings = DB::table('file_page_embeddings')
        ->where('program_file_id', $file->id)
        ->orderBy('page_number')
        ->get(['page_number', 'content', 'dimensions']);

    expect($embeddings->map(fn (object $embedding): array => [
        'page' => $embedding->page_number,
        'content' => $embedding->content,
        'dimensions' => $embedding->dimensions,
    ])->all())->toBe([
        ['page' => 1, 'content' => 'First page', 'dimensions' => 1024],
        ['page' => 2, 'content' => 'Second page', 'dimensions' => 1024],
    ]);

    $type = DB::selectOne(
        'SELECT pg_typeof(embedding)::text AS type FROM file_page_embeddings WHERE program_file_id = ? LIMIT 1',
        [$file->id],
    );

    expect($type?->type)->toBe('vector');
});

test('replaces a source file vector set when its contents change', function () {
    $file = createPgvectorProgramPdf();
    $firstPdf = '%PDF-1.4 source one';
    $secondPdf = '%PDF-1.4 source two';
    $file->forceFill(['file_size' => strlen($firstPdf)])->save();
    Storage::disk('local')->put($file->storage_key, $firstPdf);
    Process::fake([
        '*' => Process::sequence()
            ->push(Process::result("Pages:          1\n"))
            ->push(Process::result("First source text\f"))
            ->push(Process::result("Pages:          1\n"))
            ->push(Process::result("Updated source text\f")),
    ]);
    Embeddings::fake();

    $service = app(FileEmbeddingService::class);
    $service->embed($file);

    Storage::disk('local')->put($file->storage_key, $secondPdf);
    $service->embed($file->fresh());

    $embedding = DB::table('file_page_embeddings')->where('program_file_id', $file->id)->first();

    expect(DB::table('file_page_embeddings')->where('program_file_id', $file->id)->count())->toBe(1)
        ->and($embedding->source_hash)->toBe(hash('sha256', $secondPdf))
        ->and($embedding->content)->toBe('Updated source text');
});

test('fails malformed provider vectors without retrying them', function () {
    $file = createPgvectorProgramPdf();
    fakeSinglePagePdfText();
    Embeddings::fake([[array_fill(0, 1023, 0.25)]]);

    $job = (new EmbedSourceFile(false, $file->id))->withFakeQueueInteractions();
    $job->handle(app(FileEmbeddingService::class));

    $job->assertFailedWith(FileEmbeddingException::class);
    $this->assertDatabaseHas('program_files', [
        'id' => $file->id,
        'embedding_status' => FileEmbeddingStatus::Failed->value,
        'embedding_error_code' => 'invalid_embedding_response',
    ]);
    $this->assertDatabaseMissing('file_page_embeddings', ['program_file_id' => $file->id]);
});

test('fails invalid PDF content without retrying it', function () {
    $file = createPgvectorProgramPdf();
    Storage::disk('local')->put($file->storage_key, 'XPDF-1.4 test source');
    Embeddings::fake();

    $job = (new EmbedSourceFile(false, $file->id))->withFakeQueueInteractions();
    $job->handle(app(FileEmbeddingService::class));

    $job->assertFailedWith(FileEmbeddingException::class);
    $this->assertDatabaseHas('program_files', [
        'id' => $file->id,
        'embedding_status' => FileEmbeddingStatus::Failed->value,
        'embedding_error_code' => 'invalid_source_pdf',
    ]);
    Embeddings::assertNothingGenerated();
});

test('fails PDFs without extractable text without requesting embeddings', function () {
    $file = createPgvectorProgramPdf();
    Process::fake([
        '*' => Process::sequence()
            ->push(Process::result("Pages:          1\n"))
            ->push(Process::result(" \f")),
    ]);
    Embeddings::fake();

    app(FileEmbeddingService::class)->embed($file);

    $this->assertDatabaseHas('program_files', [
        'id' => $file->id,
        'embedding_status' => FileEmbeddingStatus::Failed->value,
        'embedding_error_code' => 'no_extractable_text',
    ]);
    Embeddings::assertNothingGenerated();
});

test('records provider outages with a safe retryable failure category', function () {
    $file = createPgvectorProgramPdf();
    fakeSinglePagePdfText();
    Embeddings::fake(function (EmbeddingsPrompt $prompt): never {
        throw new RuntimeException('Sensitive provider response detail.');
    });

    $job = new EmbedSourceFile(false, $file->id);
    $exception = null;

    try {
        $job->handle(app(FileEmbeddingService::class));
    } catch (FileEmbeddingException $caughtException) {
        $exception = $caughtException;
    }

    expect($exception)->toBeInstanceOf(FileEmbeddingException::class)
        ->and($exception->errorCode)->toBe('provider_failed')
        ->and($exception->retryable)->toBeTrue()
        ->and($exception->getMessage())->toBe('The embedding provider request failed.')
        ->and($exception->getPrevious())->toBeNull();

    $job->failed($exception);

    $this->assertDatabaseHas('program_files', [
        'id' => $file->id,
        'embedding_status' => FileEmbeddingStatus::Failed->value,
        'embedding_error_code' => 'provider_failed',
    ]);
});

test('marks a file failed when temporary PDF cleanup fails', function () {
    $file = createPgvectorProgramPdf();
    fakeSinglePagePdfText();
    Embeddings::fake();

    $temporaryDirectory = null;
    $filesystem = Mockery::mock(Filesystem::class)->makePartial();
    $filesystem->shouldReceive('deleteDirectory')
        ->once()
        ->andReturnUsing(function (string $directory) use (&$temporaryDirectory): bool {
            $temporaryDirectory = $directory;

            return false;
        });
    File::swap($filesystem);

    try {
        expect(fn () => app(FileEmbeddingService::class)->embed($file))
            ->toThrow(FileEmbeddingException::class, 'Temporary PDF cleanup failed.');

        expect($temporaryDirectory)->not->toBeNull()
            ->and(is_dir($temporaryDirectory))->toBeTrue();
    } finally {
        if ($temporaryDirectory !== null) {
            (new Filesystem)->deleteDirectory($temporaryDirectory);
        }

        File::swap(new Filesystem);
    }

    $this->assertDatabaseHas('program_files', [
        'id' => $file->id,
        'embedding_status' => FileEmbeddingStatus::Failed->value,
        'embedding_error_code' => 'temporary_cleanup_failed',
    ]);
});

function createPgvectorProgramPdf(): ProgramFile
{
    Storage::fake('local');

    $program = Program::factory()->create();
    $storageKey = 'program-files/'.$program->id.'/source.pdf';
    $pdf = '%PDF-1.4 test source';

    Storage::disk('local')->put($storageKey, $pdf);

    return ProgramFile::factory()->for($program)->create([
        'storage_disk' => 'local',
        'storage_key' => $storageKey,
        'file_size' => strlen($pdf),
        'mime_type' => 'application/pdf',
    ]);
}

function fakeSinglePagePdfText(): void
{
    Process::fake([
        '*' => Process::sequence()
            ->push(Process::result("Pages:          1\n"))
            ->push(Process::result("Page text\f")),
    ]);
}

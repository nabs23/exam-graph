<?php

use App\FileEmbeddingStatus;
use App\Jobs\EmbedSourceFile;
use App\Models\FilePageEmbedding;
use App\Models\Program;
use App\Models\ProgramFile;
use App\Models\Subject;
use App\Models\SubjectFile;
use App\Models\User;
use App\Services\FileEmbeddingService;
use App\Services\PdfTextExtractor;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Prompts\EmbeddingsPrompt;
use Mockery\MockInterface;

beforeEach(function (): void {
    config([
        'ai.providers.voyageai.key' => 'test-key',
        'ai.providers.voyageai.models.embeddings.default' => 'voyage-4',
        'ai.providers.voyageai.models.embeddings.dimensions' => 1024,
    ]);
});

test('content managers can queue embeddings for a program PDF', function () {
    config(['ai.file_embeddings.enabled' => true]);
    Queue::fake([EmbedSourceFile::class]);

    $program = Program::factory()->create();
    $file = ProgramFile::factory()->for($program)->create([
        'storage_key' => 'program-files/'.$program->id.'/source.pdf',
    ]);

    $this->mock(FileEmbeddingService::class, function (MockInterface $service) use ($file): void {
        $service->shouldReceive('isAvailable')->once()->andReturnTrue();
        $service->shouldReceive('queue')->once()->withArgs(fn (ProgramFile $queuedFile): bool => $queuedFile->is($file));
    });

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('program-files.embeddings.store', $file))
        ->assertRedirect();

    Queue::assertNothingPushed();
});

test('content managers can queue embeddings for a subject PDF', function () {
    config(['ai.file_embeddings.enabled' => true]);
    Queue::fake([EmbedSourceFile::class]);

    $subject = Subject::factory()->create();
    $file = SubjectFile::factory()->for($subject)->create([
        'storage_key' => 'subject-files/'.$subject->id.'/source.pdf',
    ]);

    $this->mock(FileEmbeddingService::class, function (MockInterface $service) use ($file): void {
        $service->shouldReceive('isAvailable')->once()->andReturnTrue();
        $service->shouldReceive('queue')->once()->withArgs(fn (SubjectFile $queuedFile): bool => $queuedFile->is($file));
    });

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('subject-files.embeddings.store', $file))
        ->assertRedirect();

    Queue::assertNothingPushed();
});

test('embedding requests are hidden while file embeddings are disabled', function () {
    config(['ai.file_embeddings.enabled' => false]);
    Queue::fake([EmbedSourceFile::class]);

    $file = ProgramFile::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('program-files.embeddings.store', $file))
        ->assertNotFound();

    $this->assertDatabaseHas('program_files', [
        'id' => $file->id,
        'embedding_status' => FileEmbeddingStatus::Supported->value,
    ]);

    Queue::assertNothingPushed();
});

test('content managers can request embedding assessment for a non-PDF source file', function () {
    config(['ai.file_embeddings.enabled' => true]);
    Queue::fake([EmbedSourceFile::class]);
    Embeddings::fake();

    $file = SubjectFile::factory()->create([
        'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ]);

    $this->mock(FileEmbeddingService::class, function (MockInterface $service) use ($file): void {
        $service->shouldReceive('isAvailable')->once()->andReturnTrue();
        $service->shouldReceive('queue')->once()->withArgs(fn (SubjectFile $queuedFile): bool => $queuedFile->is($file));
    });

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('subject-files.embeddings.store', $file))
        ->assertRedirect();

    Queue::assertNothingPushed();
    Embeddings::assertNothingGenerated();
});

test('only PDFs are supported for text embedding', function () {
    expect(FileEmbeddingStatus::forMimeType('application/pdf'))->toBe(FileEmbeddingStatus::Supported)
        ->and(FileEmbeddingStatus::forMimeType('application/vnd.openxmlformats-officedocument.wordprocessingml.document'))->toBe(FileEmbeddingStatus::Unsupported);
});

test('embedding generation uses the configured VoyageAI text model', function () {
    config([
        'ai.file_embeddings.enabled' => true,
        'ai.providers.voyageai.models.embeddings.default' => 'voyage-4',
        'ai.providers.voyageai.models.embeddings.dimensions' => 1024,
    ]);
    Embeddings::fake();

    $vectors = app(FileEmbeddingService::class)->generate(['Linear equations have one variable.']);

    expect($vectors)->toHaveCount(1)
        ->and($vectors[0])->toHaveCount(1024);

    Embeddings::assertGenerated(fn (EmbeddingsPrompt $prompt): bool => $prompt->provider->name() === 'voyageai'
        && $prompt->model === 'voyage-4'
        && $prompt->dimensions === 1024
        && $prompt->inputs[0] === 'Linear equations have one variable.'
    );
});

test('PDF text extraction preserves page numbers and excludes empty pages', function () {
    Process::fake([
        '*' => Process::sequence()
            ->push(Process::result("Pages:          3\n"))
            ->push(Process::result("First page\fSecond page\f \f")),
    ]);

    $pages = app(PdfTextExtractor::class)->extract('/tmp/source.pdf');

    expect($pages)->toBe([
        ['page' => 1, 'content' => 'First page'],
        ['page' => 2, 'content' => 'Second page'],
    ]);

    Process::assertRan(fn ($process): bool => in_array('pdfinfo', $process->command, true));
    Process::assertRan(fn ($process): bool => in_array('pdftotext', $process->command, true));
});

test('deleting a source file deletes its page embeddings', function () {
    $file = ProgramFile::factory()->create();
    $embedding = FilePageEmbedding::query()->create([
        'program_file_id' => $file->id,
        'source_key' => 'program_file:'.$file->id,
        'page_number' => 1,
        'source_hash' => str_repeat('a', 64),
        'provider' => 'voyageai',
        'model' => 'voyage-4',
        'dimensions' => 1024,
        'content' => 'Revenue is recognized when control transfers.',
        'embedding' => array_fill(0, 1024, 0.0),
        'embedded_at' => now(),
    ]);

    $file->delete();

    $this->assertDatabaseMissing('file_page_embeddings', ['id' => $embedding->id]);
});

test('content managers cannot queue embeddings for files they cannot manage', function () {
    config(['ai.file_embeddings.enabled' => true]);
    Queue::fake([EmbedSourceFile::class]);

    $file = ProgramFile::factory()->create();

    $this->actingAs(User::factory()->create())
        ->post(route('program-files.embeddings.store', $file))
        ->assertForbidden();

    Queue::assertNothingPushed();
});

test('an exhausted embedding job records a safe failure state', function () {
    $file = ProgramFile::factory()->create();

    (new EmbedSourceFile(false, $file->id))->failed(new RuntimeException('Provider error detail'));

    $this->assertDatabaseHas('program_files', [
        'id' => $file->id,
        'embedding_status' => FileEmbeddingStatus::Failed->value,
        'embedding_error_code' => 'embedding_failed',
    ]);
});

test('embedding jobs use the configured retry bounds', function () {
    config([
        'ai.file_embeddings.job_tries' => 2,
        'ai.file_embeddings.job_backoff' => [15, 45],
        'ai.file_embeddings.job_timeout' => 150,
    ]);

    $job = new EmbedSourceFile(false, 1);

    expect($job->tries)->toBe(2)
        ->and($job->backoff())->toBe([15, 45])
        ->and($job->timeout)->toBe(150);
});

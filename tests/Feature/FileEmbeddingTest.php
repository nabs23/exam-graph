<?php

use App\FileEmbeddingStatus;
use App\Jobs\EmbedSourceFile;
use App\Models\Program;
use App\Models\ProgramFile;
use App\Models\Subject;
use App\Models\SubjectFile;
use App\Models\User;
use App\Services\FileEmbeddingService;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Files\Image;
use Laravel\Ai\Prompts\EmbeddingsPrompt;

test('content managers can queue embeddings for a program PDF', function () {
    config(['ai.file_embeddings.enabled' => true]);
    Queue::fake([EmbedSourceFile::class]);

    $program = Program::factory()->create();
    $file = ProgramFile::factory()->for($program)->create([
        'storage_key' => 'program-files/'.$program->id.'/source.pdf',
    ]);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('program-files.embeddings.store', $file))
        ->assertRedirect();

    $this->assertDatabaseHas('program_files', [
        'id' => $file->id,
        'embedding_status' => FileEmbeddingStatus::Queued->value,
    ]);

    Queue::assertPushed(EmbedSourceFile::class, fn (EmbedSourceFile $job): bool => ! $job->isSubjectFile && $job->fileId === $file->id);
});

test('content managers can queue embeddings for a subject PDF', function () {
    config(['ai.file_embeddings.enabled' => true]);
    Queue::fake([EmbedSourceFile::class]);

    $subject = Subject::factory()->create();
    $file = SubjectFile::factory()->for($subject)->create([
        'storage_key' => 'subject-files/'.$subject->id.'/source.pdf',
    ]);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('subject-files.embeddings.store', $file))
        ->assertRedirect();

    $this->assertDatabaseHas('subject_files', [
        'id' => $file->id,
        'embedding_status' => FileEmbeddingStatus::Queued->value,
    ]);

    Queue::assertPushed(EmbedSourceFile::class, fn (EmbedSourceFile $job): bool => $job->isSubjectFile && $job->fileId === $file->id);
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
        'embedding_status' => FileEmbeddingStatus::NotRequested->value,
    ]);

    Queue::assertNothingPushed();
});

test('non-PDF source files are marked unsupported without contacting the provider', function () {
    config(['ai.file_embeddings.enabled' => true]);
    Queue::fake([EmbedSourceFile::class]);
    Embeddings::fake();

    $file = SubjectFile::factory()->create([
        'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ]);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('subject-files.embeddings.store', $file))
        ->assertRedirect();

    $this->assertDatabaseHas('subject_files', [
        'id' => $file->id,
        'embedding_status' => FileEmbeddingStatus::Unsupported->value,
        'embedding_error_code' => 'unsupported_file_type',
    ]);

    Queue::assertNothingPushed();
    Embeddings::assertNothingGenerated();
});

test('embedding generation uses the configured VoyageAI multimodal model', function () {
    config([
        'ai.file_embeddings.enabled' => true,
        'ai.providers.voyageai.models.embeddings.default' => 'voyage-multimodal-3.5',
        'ai.providers.voyageai.models.embeddings.dimensions' => 1024,
    ]);
    Embeddings::fake();

    $imagePath = tempnam(sys_get_temp_dir(), 'examgraph-image-');
    file_put_contents($imagePath, 'fake image bytes');

    try {
        $vectors = app(FileEmbeddingService::class)->generate([Image::fromPath($imagePath)]);
    } finally {
        unlink($imagePath);
    }

    expect($vectors)->toHaveCount(1)
        ->and($vectors[0])->toHaveCount(1024);

    Embeddings::assertGenerated(fn (EmbeddingsPrompt $prompt): bool => $prompt->provider->name() === 'voyageai'
        && $prompt->model === 'voyage-multimodal-3.5'
        && $prompt->dimensions === 1024
        && $prompt->inputs[0] instanceof Image
    );
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

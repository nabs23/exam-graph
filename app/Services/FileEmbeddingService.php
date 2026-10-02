<?php

namespace App\Services;

use App\Exceptions\FileEmbeddingException;
use App\FileEmbeddingStatus;
use App\FileUploadStatus;
use App\Jobs\EmbedSourceFile;
use App\Models\FilePageEmbedding;
use App\Models\ProgramFile;
use App\Models\SubjectFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Exceptions\RateLimitedException;
use LogicException;
use RuntimeException;
use Throwable;

class FileEmbeddingService
{
    public function __construct(private PdfTextExtractor $pdfTextExtractor) {}

    public function isEnabled(): bool
    {
        return (bool) config('ai.file_embeddings.enabled', false);
    }

    public function isAvailable(): bool
    {
        return $this->unavailableReason() === null;
    }

    public function unavailableReason(): ?string
    {
        try {
            $this->assertReady();

            return null;
        } catch (Throwable $exception) {
            return match ($exception->getMessage()) {
                'File embeddings are disabled.' => 'Embedding generation is disabled in the application configuration.',
                'File embeddings require PostgreSQL with pgvector.' => 'PostgreSQL with pgvector is required.',
                'File embedding migrations have not been applied.' => 'The file embedding database migration has not been applied.',
                'The pgvector extension is not enabled for this database.' => 'The pgvector extension is not enabled for this database.',
                'The VoyageAI API key is not configured.' => 'The VoyageAI API key is not configured.',
                'The configured VoyageAI model does not support text embeddings.' => 'Choose a supported VoyageAI text embedding model.',
                'The configured VoyageAI embedding dimension does not match the installed vector column.' => 'The configured embedding dimension does not match the database vector column.',
                default => 'Embedding requirements are unavailable. Check the database, pgvector, provider, model, and dimension configuration.',
            };
        }
    }

    public function queue(ProgramFile|SubjectFile $file): void
    {
        $this->assertReady();

        if ($file->upload_status !== FileUploadStatus::Uploaded) {
            throw new LogicException('Only completed source file uploads can be embedded.');
        }

        if ($file->mime_type !== 'application/pdf') {
            $file->forceFill([
                'embedding_status' => FileEmbeddingStatus::Unsupported,
                'embedding_error_code' => 'unsupported_file_type',
                'embedded_at' => null,
            ])->save();

            return;
        }

        $expectedPrefix = $file instanceof SubjectFile
            ? 'subject-files/'.$file->subject_id.'/'
            : 'program-files/'.$file->program_id.'/';

        if (! str_starts_with($file->storage_key, $expectedPrefix)) {
            throw new RuntimeException('The source file storage key is outside its expected owner path.');
        }

        if (in_array($file->embedding_status, [FileEmbeddingStatus::Queued, FileEmbeddingStatus::Processing], true)) {
            return;
        }

        $model = (string) config('ai.providers.voyageai.models.embeddings.default');

        if ($file->embedding_status === FileEmbeddingStatus::Complete && $file->embedding_model === $model) {
            return;
        }

        $file->forceFill([
            'embedding_status' => FileEmbeddingStatus::Queued,
            'embedding_error_code' => null,
        ])->save();

        EmbedSourceFile::dispatch($file instanceof SubjectFile, $file->id)->afterCommit();
    }

    public function embed(ProgramFile|SubjectFile $file): void
    {
        $this->assertReady();

        if ($file->upload_status !== FileUploadStatus::Uploaded) {
            return;
        }

        if ($file->mime_type !== 'application/pdf') {
            $file->forceFill([
                'embedding_status' => FileEmbeddingStatus::Unsupported,
                'embedding_error_code' => 'unsupported_file_type',
            ])->save();

            return;
        }

        $expectedPrefix = $file instanceof SubjectFile
            ? 'subject-files/'.$file->subject_id.'/'
            : 'program-files/'.$file->program_id.'/';

        if (! str_starts_with($file->storage_key, $expectedPrefix)) {
            throw new RuntimeException('The source file storage key is outside its expected owner path.');
        }

        $file->forceFill([
            'embedding_status' => FileEmbeddingStatus::Processing,
            'embedding_error_code' => null,
        ])->save();

        $temporaryDirectory = sys_get_temp_dir().'/examgraph-embedding-'.Str::uuid();
        File::ensureDirectoryExists($temporaryDirectory);

        try {
            $pdfPath = $temporaryDirectory.'/source.pdf';
            $sourceHash = $this->copySourceToLocalFile($file, $pdfPath);
            $pages = $this->pdfTextExtractor->extract($pdfPath);

            if ($pages === []) {
                $file->forceFill([
                    'embedding_status' => FileEmbeddingStatus::Failed,
                    'embedding_error_code' => 'no_extractable_text',
                ])->save();

                return;
            }

            $vectors = $this->generate(array_column($pages, 'content'));

            if (count($vectors) !== count($pages)) {
                throw new FileEmbeddingException('invalid_embedding_response', false, 'The embedding provider returned an unexpected vector count.');
            }

            $dimensions = $this->dimensions();

            foreach ($vectors as $vector) {
                if (! array_is_list($vector) || count($vector) !== $dimensions) {
                    throw new FileEmbeddingException('invalid_embedding_response', false, 'The embedding provider returned an invalid vector.');
                }

                foreach ($vector as $value) {
                    if (! is_numeric($value) || ! is_finite((float) $value)) {
                        throw new FileEmbeddingException('invalid_embedding_response', false, 'The embedding provider returned an invalid vector.');
                    }
                }
            }

            $this->persist($file, $pages, $vectors, $sourceHash, $dimensions);

            $file->forceFill([
                'content_hash' => $sourceHash,
                'embedding_status' => FileEmbeddingStatus::Complete,
                'embedding_error_code' => null,
                'embedding_model' => (string) config('ai.providers.voyageai.models.embeddings.default'),
                'embedded_at' => now(),
            ])->save();
        } finally {
            try {
                File::deleteDirectory($temporaryDirectory);
                $temporaryFilesRemoved = ! File::exists($temporaryDirectory);
            } catch (Throwable) {
                $temporaryFilesRemoved = false;
            }

            if (! $temporaryFilesRemoved) {
                $file->forceFill([
                    'embedding_status' => FileEmbeddingStatus::Failed,
                    'embedding_error_code' => 'temporary_cleanup_failed',
                ])->save();

                throw new FileEmbeddingException('temporary_cleanup_failed', true, 'Temporary PDF cleanup failed.');
            }
        }
    }

    /** @param array<int, string> $texts
     * @return array<int, array<float|int>>
     */
    public function generate(array $texts): array
    {
        if (! $this->isEnabled()) {
            throw new LogicException('File embeddings are disabled.');
        }

        $vectors = [];
        $batchSize = max(1, min(64, (int) config('ai.file_embeddings.batch_size', 8)));

        foreach (array_chunk($texts, $batchSize) as $batch) {
            try {
                $response = Embeddings::for($batch)
                    ->dimensions($this->dimensions())
                    ->timeout((int) config('ai.file_embeddings.timeout', 40))
                    ->generate(Lab::VoyageAI, (string) config('ai.providers.voyageai.models.embeddings.default'));
            } catch (Throwable $exception) {
                $cause = $exception;

                while ($cause->getPrevious() !== null) {
                    $cause = $cause->getPrevious();
                }

                Log::warning('File embedding provider request failed.', [
                    'provider' => 'voyageai',
                    'model' => (string) config('ai.providers.voyageai.models.embeddings.default'),
                    'exception_class' => $exception::class,
                    'cause_class' => $cause::class,
                    'input_count' => count($batch),
                    'input_bytes' => array_sum(array_map('strlen', $batch)),
                ]);

                throw new FileEmbeddingException(
                    $exception instanceof RateLimitedException ? 'provider_rate_limited' : 'provider_failed',
                    true,
                    $exception instanceof RateLimitedException
                        ? 'The embedding provider rate limit was reached.'
                        : 'The embedding provider request failed.',
                );
            }

            if (count($response->embeddings) !== count($batch)) {
                throw new FileEmbeddingException('invalid_embedding_response', false, 'The embedding provider returned an unexpected vector count.');
            }

            array_push($vectors, ...$response->embeddings);
        }

        return $vectors;
    }

    private function assertReady(): void
    {
        if (! $this->isEnabled()) {
            throw new LogicException('File embeddings are disabled.');
        }

        if (DB::connection()->getDriverName() !== 'pgsql') {
            throw new RuntimeException('File embeddings require PostgreSQL with pgvector.');
        }

        if (! Schema::hasTable('file_page_embeddings')) {
            throw new RuntimeException('File embedding migrations have not been applied.');
        }

        if (! DB::table('pg_extension')->where('extname', 'vector')->exists()) {
            throw new RuntimeException('The pgvector extension is not enabled for this database.');
        }

        if (blank(config('ai.providers.voyageai.key'))) {
            throw new RuntimeException('The VoyageAI API key is not configured.');
        }

        if (! in_array((string) config('ai.providers.voyageai.models.embeddings.default'), ['voyage-4-large', 'voyage-4', 'voyage-4-lite'], true)) {
            throw new RuntimeException('The configured VoyageAI model does not support text embeddings.');
        }

        $column = DB::selectOne(
            'SELECT format_type(attributes.atttypid, attributes.atttypmod) AS type
             FROM pg_attribute AS attributes
             INNER JOIN pg_class AS classes ON classes.oid = attributes.attrelid
             INNER JOIN pg_namespace AS namespaces ON namespaces.oid = classes.relnamespace
             WHERE namespaces.nspname = current_schema()
               AND classes.relname = ?
               AND attributes.attname = ?
               AND attributes.attnum > 0
               AND NOT attributes.attisdropped',
            ['file_page_embeddings', 'embedding'],
        );

        if ($column?->type !== 'vector('.$this->dimensions().')') {
            throw new RuntimeException('The configured VoyageAI embedding dimension does not match the installed vector column.');
        }
    }

    private function dimensions(): int
    {
        return (int) config('ai.providers.voyageai.models.embeddings.dimensions', 1024);
    }

    private function copySourceToLocalFile(ProgramFile|SubjectFile $file, string $destination): string
    {
        $source = Storage::disk($file->storage_disk)->readStream($file->storage_key);
        $target = fopen($destination, 'wb');

        if (! is_resource($source) || ! is_resource($target)) {
            if (is_resource($source)) {
                fclose($source);
            }

            if (is_resource($target)) {
                fclose($target);
            }

            throw new RuntimeException('The source file could not be read from storage.');
        }

        try {
            if (stream_copy_to_stream($source, $target) === false) {
                throw new RuntimeException('The source file could not be copied for processing.');
            }
        } finally {
            fclose($source);
            fclose($target);
        }

        if (filesize($destination) < 5 || file_get_contents($destination, false, null, 0, 5) !== '%PDF-') {
            throw new FileEmbeddingException('invalid_source_pdf', false, 'The source file is not a valid PDF.');
        }

        $fileSize = filesize($destination);

        if ($fileSize > 104857600 || ($file->file_size !== null && $fileSize !== $file->file_size)) {
            throw new FileEmbeddingException('source_file_size_mismatch', false, 'The source file size does not match its verified upload record.');
        }

        $sourceHash = hash_file('sha256', $destination);

        if ($sourceHash === false) {
            throw new RuntimeException('The source file hash could not be computed.');
        }

        return $sourceHash;
    }

    /** @param array<int, array{page: int, content: string}> $pages
     * @param  array<int, array<float|int>>  $vectors
     */
    private function persist(ProgramFile|SubjectFile $file, array $pages, array $vectors, string $sourceHash, int $dimensions): void
    {
        $isSubjectFile = $file instanceof SubjectFile;
        $sourceKey = ($isSubjectFile ? 'subject_file:' : 'program_file:').$file->getKey();
        $programFileId = $isSubjectFile ? null : $file->getKey();
        $subjectFileId = $isSubjectFile ? $file->getKey() : null;

        DB::transaction(function () use ($pages, $vectors, $sourceHash, $dimensions, $sourceKey, $programFileId, $subjectFileId): void {
            FilePageEmbedding::query()->where('source_key', $sourceKey)->delete();

            foreach ($pages as $index => $page) {
                $now = now();

                DB::insert(
                    'INSERT INTO file_page_embeddings (program_file_id, subject_file_id, source_key, page_number, source_hash, provider, model, dimensions, content, embedding, embedded_at, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?::vector, ?, ?, ?)',
                    [
                        $programFileId,
                        $subjectFileId,
                        $sourceKey,
                        $page['page'],
                        $sourceHash,
                        'voyageai',
                        (string) config('ai.providers.voyageai.models.embeddings.default'),
                        $dimensions,
                        $page['content'],
                        json_encode($vectors[$index], JSON_THROW_ON_ERROR),
                        $now,
                        $now,
                        $now,
                    ],
                );
            }
        });
    }
}

<?php

namespace App\Services;

use App\FileEmbeddingStatus;
use App\FileUploadStatus;
use App\Jobs\EmbedSourceFile;
use App\Models\FilePageEmbedding;
use App\Models\ProgramFile;
use App\Models\SubjectFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Files\Image;
use LogicException;
use RuntimeException;

class FileEmbeddingService
{
    public function __construct(private PdfPageRenderer $pdfPageRenderer) {}

    public function isEnabled(): bool
    {
        return (bool) config('ai.file_embeddings.enabled', false);
    }

    public function isAvailable(): bool
    {
        try {
            $this->assertReady();

            return true;
        } catch (\Throwable) {
            return false;
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
            $pageFiles = $this->pdfPageRenderer->render($pdfPath, $temporaryDirectory.'/pages');
            $pageImages = array_map(fn (array $page): Image => Image::fromPath($page['path']), $pageFiles);

            foreach ($pageFiles as $page) {
                if (filesize($page['path']) > 20 * 1024 * 1024) {
                    throw new RuntimeException('A rendered PDF page exceeds the supported image size.');
                }
            }

            $vectors = $this->generate($pageImages);

            if (count($vectors) !== count($pageFiles)) {
                throw new RuntimeException('The embedding provider returned an unexpected vector count.');
            }

            $dimensions = $this->dimensions();

            foreach ($vectors as $vector) {
                if (! array_is_list($vector) || count($vector) !== $dimensions) {
                    throw new RuntimeException('The embedding provider returned an invalid vector.');
                }

                foreach ($vector as $value) {
                    if (! is_numeric($value) || ! is_finite((float) $value)) {
                        throw new RuntimeException('The embedding provider returned an invalid vector.');
                    }
                }
            }

            $this->persist($file, $pageFiles, $vectors, $sourceHash, $dimensions);

            $file->forceFill([
                'content_hash' => $sourceHash,
                'embedding_status' => FileEmbeddingStatus::Complete,
                'embedding_error_code' => null,
                'embedding_model' => (string) config('ai.providers.voyageai.models.embeddings.default'),
                'embedded_at' => now(),
            ])->save();
        } finally {
            File::deleteDirectory($temporaryDirectory);
        }
    }

    /** @param array<int, Image> $images
     * @return array<int, array<float|int>>
     */
    public function generate(array $images): array
    {
        if (! $this->isEnabled()) {
            throw new LogicException('File embeddings are disabled.');
        }

        $response = Embeddings::for($images)
            ->dimensions($this->dimensions())
            ->timeout((int) config('ai.file_embeddings.timeout', 40))
            ->generate(Lab::VoyageAI, (string) config('ai.providers.voyageai.models.embeddings.default'));

        return $response->embeddings;
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

        if (! in_array((string) config('ai.providers.voyageai.models.embeddings.default'), ['voyage-multimodal-3', 'voyage-multimodal-3.5'], true)) {
            throw new RuntimeException('The configured VoyageAI model does not support image embeddings.');
        }

        if ($this->dimensions() !== 1024) {
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
            throw new RuntimeException('The source file is not a valid PDF.');
        }

        $fileSize = filesize($destination);

        if ($fileSize > 104857600 || ($file->file_size !== null && $fileSize !== $file->file_size)) {
            throw new RuntimeException('The source file size does not match its verified upload record.');
        }

        $sourceHash = hash_file('sha256', $destination);

        if ($sourceHash === false) {
            throw new RuntimeException('The source file hash could not be computed.');
        }

        return $sourceHash;
    }

    /** @param array<int, array{page: int, path: string}> $pageFiles
     * @param  array<int, array<float|int>>  $vectors
     */
    private function persist(ProgramFile|SubjectFile $file, array $pageFiles, array $vectors, string $sourceHash, int $dimensions): void
    {
        $isSubjectFile = $file instanceof SubjectFile;
        $sourceKey = ($isSubjectFile ? 'subject_file:' : 'program_file:').$file->getKey();
        $programFileId = $isSubjectFile ? null : $file->getKey();
        $subjectFileId = $isSubjectFile ? $file->getKey() : null;

        DB::transaction(function () use ($pageFiles, $vectors, $sourceHash, $dimensions, $sourceKey, $programFileId, $subjectFileId): void {
            FilePageEmbedding::query()->where('source_key', $sourceKey)->delete();

            foreach ($pageFiles as $index => $page) {
                $now = now();

                DB::insert(
                    'INSERT INTO file_page_embeddings (program_file_id, subject_file_id, source_key, page_number, source_hash, provider, model, dimensions, embedding, embedded_at, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?::vector, ?, ?, ?)',
                    [
                        $programFileId,
                        $subjectFileId,
                        $sourceKey,
                        $page['page'],
                        $sourceHash,
                        'voyageai',
                        (string) config('ai.providers.voyageai.models.embeddings.default'),
                        $dimensions,
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

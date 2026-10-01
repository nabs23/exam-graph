<?php

namespace App\Jobs;

use App\Exceptions\FileEmbeddingException;
use App\FileEmbeddingStatus;
use App\Models\ProgramFile;
use App\Models\SubjectFile;
use App\Services\FileEmbeddingService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class EmbedSourceFile implements ShouldBeUnique, ShouldQueueAfterCommit
{
    use Queueable;

    public int $tries;

    public int $timeout;

    public int $uniqueFor = 3600;

    public function __construct(public bool $isSubjectFile, public int $fileId)
    {
        $this->tries = (int) config('ai.file_embeddings.job_tries', 3);
        $this->timeout = (int) config('ai.file_embeddings.job_timeout', 120);
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return config('ai.file_embeddings.job_backoff', [10, 30]);
    }

    public function handle(FileEmbeddingService $fileEmbeddingService): void
    {
        $file = $this->isSubjectFile
            ? SubjectFile::query()->find($this->fileId)
            : ProgramFile::query()->find($this->fileId);

        if ($file !== null) {
            try {
                $fileEmbeddingService->embed($file);
            } catch (FileEmbeddingException $exception) {
                if ($exception->retryable) {
                    throw $exception;
                }

                $file->forceFill([
                    'embedding_status' => FileEmbeddingStatus::Failed,
                    'embedding_error_code' => $exception->errorCode,
                ])->save();

                $this->fail($exception);
            }
        }
    }

    public function uniqueId(): string
    {
        return ($this->isSubjectFile ? 'subject:' : 'program:').$this->fileId;
    }

    public function failed(?Throwable $exception): void
    {
        $file = $this->isSubjectFile
            ? SubjectFile::query()->find($this->fileId)
            : ProgramFile::query()->find($this->fileId);

        if ($file !== null) {
            $file->forceFill([
                'embedding_status' => FileEmbeddingStatus::Failed,
                'embedding_error_code' => $exception instanceof FileEmbeddingException
                    ? $exception->errorCode
                    : 'embedding_failed',
            ])->save();
        }
    }
}

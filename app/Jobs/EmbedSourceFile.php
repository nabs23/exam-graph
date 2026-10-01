<?php

namespace App\Jobs;

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

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 30];

    public int $timeout = 80;

    public int $uniqueFor = 3600;

    public function __construct(public bool $isSubjectFile, public int $fileId) {}

    public function handle(FileEmbeddingService $fileEmbeddingService): void
    {
        $file = $this->isSubjectFile
            ? SubjectFile::query()->find($this->fileId)
            : ProgramFile::query()->find($this->fileId);

        if ($file !== null) {
            $fileEmbeddingService->embed($file);
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
                'embedding_error_code' => 'embedding_failed',
            ])->save();
        }
    }
}

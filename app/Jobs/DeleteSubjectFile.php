<?php

namespace App\Jobs;

use App\Models\SubjectFile;
use App\Services\SourceFileStorage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DeleteSubjectFile implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public int $fileId)
    {
        $file = SubjectFile::query()->whereKey($this->fileId)->where('upload_status', 'delete_pending')->first();

        if ($file !== null) {
            app(SourceFileStorage::class)->delete($file);
        }
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        //
    }
}

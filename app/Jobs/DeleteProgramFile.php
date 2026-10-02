<?php

namespace App\Jobs;

use App\Models\ProgramFile;
use App\Services\SourceFileStorage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DeleteProgramFile implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public int $fileId) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $file = ProgramFile::query()->whereKey($this->fileId)->where('upload_status', 'delete_pending')->first();

        if ($file !== null) {
            app(SourceFileStorage::class)->delete($file);
        }
    }
}

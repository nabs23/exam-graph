<?php

namespace App\Console\Commands;

use App\Models\ProgramFile;
use App\Models\SubjectFile;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('app:cleanup-pending-file-uploads')]
#[Description('Remove expired pending source-file uploads.')]
class CleanupPendingFileUploads extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        foreach ([ProgramFile::class => 'program-files/', SubjectFile::class => 'subject-files/'] as $model => $prefix) {
            $model::query()->where('upload_status', 'pending_upload')->where('created_at', '<', now()->subMinutes(15))->chunkById(100, function ($files) use ($prefix): void {
                foreach ($files as $file) {
                    if (! str_starts_with($file->storage_key, $prefix) || ! preg_match('/^[a-z-]+\/\d+\/[0-9a-f-]{36}\.(pdf|docx|epub)$/i', $file->storage_key)) {
                        continue;
                    }

                    Storage::disk($file->storage_disk)->delete($file->storage_key);
                    $file->delete();
                }
            });
        }

        return self::SUCCESS;
    }
}

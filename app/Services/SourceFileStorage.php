<?php

namespace App\Services;

use App\FileUploadStatus;
use App\Models\ProgramFile;
use App\Models\SubjectFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class SourceFileStorage
{
    public function createKey(string $prefix, string $ownerId, string $extension): string
    {
        return $prefix.'/'.$ownerId.'/'.Str::uuid().'.'.$extension;
    }

    /** @return array{url: string, headers: array<string, string>} */
    public function presign(string $key, string $mimeType): array
    {
        $disk = Storage::disk('s3');

        if (! method_exists($disk, 'temporaryUploadUrl')) {
            throw new RuntimeException('S3 direct uploads are not configured. Install and configure the AWS S3 Flysystem adapter.');
        }

        return $disk->temporaryUploadUrl($key, now()->addMinutes(5), ['ContentType' => $mimeType]);
    }

    public function delete(ProgramFile|SubjectFile $file): void
    {
        $disk = Storage::disk($file->storage_disk);

        if ($disk->exists($file->storage_key) && ! $disk->delete($file->storage_key)) {
            throw new RuntimeException('The source file object could not be deleted.');
        }

        $file->delete();
    }

    public function markFailed(ProgramFile|SubjectFile $file): void
    {
        $file->forceFill(['upload_status' => FileUploadStatus::Failed])->save();
    }
}

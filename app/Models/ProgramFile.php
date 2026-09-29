<?php

namespace App\Models;

use App\FileUploadStatus;
use App\ProgramFileType;
use Database\Factories\ProgramFileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgramFile extends Model
{
    /** @use HasFactory<ProgramFileFactory> */
    use HasFactory;

    protected $fillable = ['file_type', 'title', 'original_filename', 'storage_key', 'mime_type', 'file_size', 'uploaded_by', 'metadata'];

    protected $hidden = ['storage_key', 'storage_disk'];

    protected function casts(): array
    {
        return [
            'file_type' => ProgramFileType::class,
            'upload_status' => FileUploadStatus::class,
            'metadata' => 'array',
            'uploaded_at' => 'datetime',
        ];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}

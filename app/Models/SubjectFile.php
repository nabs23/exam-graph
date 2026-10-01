<?php

namespace App\Models;

use App\FileEmbeddingStatus;
use App\FileUploadStatus;
use App\SubjectFileType;
use Database\Factories\SubjectFileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubjectFile extends Model
{
    /** @use HasFactory<SubjectFileFactory> */
    use HasFactory;

    protected $fillable = ['file_type', 'title', 'original_filename', 'storage_key', 'mime_type', 'file_size', 'uploaded_by', 'metadata'];

    protected $hidden = ['storage_key', 'storage_disk'];

    protected function casts(): array
    {
        return [
            'file_type' => SubjectFileType::class,
            'upload_status' => FileUploadStatus::class,
            'embedding_status' => FileEmbeddingStatus::class,
            'metadata' => 'array',
            'uploaded_at' => 'datetime',
            'embedded_at' => 'datetime',
        ];
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function pageEmbeddings(): HasMany
    {
        return $this->hasMany(FilePageEmbedding::class);
    }
}

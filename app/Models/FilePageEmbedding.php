<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FilePageEmbedding extends Model
{
    protected $fillable = [
        'program_file_id',
        'subject_file_id',
        'source_key',
        'page_number',
        'source_hash',
        'provider',
        'model',
        'dimensions',
        'embedding',
        'embedded_at',
    ];

    protected function casts(): array
    {
        return [
            'page_number' => 'integer',
            'dimensions' => 'integer',
            'embedding' => 'array',
            'embedded_at' => 'datetime',
        ];
    }

    public function programFile(): BelongsTo
    {
        return $this->belongsTo(ProgramFile::class);
    }

    public function subjectFile(): BelongsTo
    {
        return $this->belongsTo(SubjectFile::class);
    }
}

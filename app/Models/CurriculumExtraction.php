<?php

namespace App\Models;

use App\CurriculumExtractionError;
use App\CurriculumExtractionStatus;
use Database\Factories\CurriculumExtractionFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CurriculumExtraction extends Model
{
    /** @use HasFactory<CurriculumExtractionFactory> */
    use HasFactory;

    protected $fillable = [
        'program_id', 'program_file_id', 'requested_by', 'reviewed_by', 'status', 'source_hash', 'source_files',
        'provider', 'model', 'prompt_version', 'proposal', 'reviewed_proposal', 'error_code', 'reviewed_at',
    ];

    protected $appends = ['error_message'];

    protected $hidden = ['provider_response_id', 'provider_started_at'];

    protected function errorMessage(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->error_code === null
            ? null
            : (CurriculumExtractionError::tryFrom($this->error_code) ?? CurriculumExtractionError::ExtractionFailed)->message());
    }

    protected function casts(): array
    {
        return [
            'status' => CurriculumExtractionStatus::class,
            'proposal' => 'array',
            'reviewed_proposal' => 'array',
            'source_files' => 'array',
            'reviewed_at' => 'datetime',
            'provider_started_at' => 'datetime',
        ];
    }

    public function programFile(): BelongsTo
    {
        return $this->belongsTo(ProgramFile::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}

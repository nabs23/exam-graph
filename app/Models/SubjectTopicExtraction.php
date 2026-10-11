<?php

namespace App\Models;

use App\SubjectTopicExtractionError;
use App\SubjectTopicExtractionStatus;
use Database\Factories\SubjectTopicExtractionFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubjectTopicExtraction extends Model
{
    /** @use HasFactory<SubjectTopicExtractionFactory> */
    use HasFactory;

    protected $fillable = [
        'subject_id', 'requested_by', 'reviewed_by', 'status', 'source_hash', 'source_files', 'provider', 'model',
        'prompt_version', 'proposal', 'reviewed_proposal', 'error_code', 'correlation_id', 'reviewed_at',
    ];

    protected $appends = ['error_message'];

    protected function casts(): array
    {
        return [
            'status' => SubjectTopicExtractionStatus::class,
            'source_files' => 'array',
            'proposal' => 'array',
            'reviewed_proposal' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    protected function errorMessage(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->error_code === null
            ? null
            : (SubjectTopicExtractionError::tryFrom($this->error_code) ?? SubjectTopicExtractionError::ExtractionFailed)->message());
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
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

<?php

namespace App\Models;

use App\CurriculumGenerationError;
use App\CurriculumGenerationKind;
use App\CurriculumGenerationStatus;
use Database\Factories\CurriculumGenerationFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CurriculumGeneration extends Model
{
    /** @use HasFactory<CurriculumGenerationFactory> */
    use HasFactory;

    protected $appends = ['error_message'];

    protected $fillable = [
        'program_id', 'subject_id', 'syllabus_topic_id', 'requested_by', 'reviewed_by', 'kind', 'status',
        'request_context', 'ungrounded_requested', 'request_hash', 'source_hash', 'source_files', 'provider', 'model', 'prompt_version',
        'schema_version', 'scope_snapshot', 'proposal', 'reviewed_proposal', 'error_code', 'correlation_id', 'generated_at', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'kind' => CurriculumGenerationKind::class,
            'status' => CurriculumGenerationStatus::class,
            'ungrounded_requested' => 'boolean',
            'source_files' => 'array',
            'scope_snapshot' => 'array',
            'proposal' => 'array',
            'reviewed_proposal' => 'array',
            'generated_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    protected function errorMessage(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->error_code === null
            ? null
            : (CurriculumGenerationError::tryFrom($this->error_code) ?? CurriculumGenerationError::ProviderFailed)->message());
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function syllabusTopic(): BelongsTo
    {
        return $this->belongsTo(SyllabusTopic::class);
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

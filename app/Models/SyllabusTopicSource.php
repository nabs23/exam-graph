<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyllabusTopicSource extends Model
{
    protected $fillable = [
        'syllabus_topic_id', 'subject_file_id', 'program_file_id', 'source_file_id_snapshot', 'source_title', 'source_hash', 'page_number',
    ];

    protected function casts(): array
    {
        return ['page_number' => 'integer', 'source_file_id_snapshot' => 'integer'];
    }

    public function syllabusTopic(): BelongsTo
    {
        return $this->belongsTo(SyllabusTopic::class);
    }

    public function subjectFile(): BelongsTo
    {
        return $this->belongsTo(SubjectFile::class);
    }

    public function programFile(): BelongsTo
    {
        return $this->belongsTo(ProgramFile::class);
    }
}

<?php

namespace App\Models;

use Database\Factories\SubjectFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subject extends Model
{
    /** @use HasFactory<SubjectFactory> */
    use HasFactory;

    protected $fillable = ['program_id', 'name', 'code', 'description', 'sort_order'];

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function syllabusTopics(): HasMany
    {
        return $this->hasMany(SyllabusTopic::class);
    }

    public function concepts(): HasMany
    {
        return $this->hasMany(Concept::class);
    }
}

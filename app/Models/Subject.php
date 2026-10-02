<?php

namespace App\Models;

use App\Concerns\Models\HasCurriculumRoutes;
use Database\Factories\SubjectFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subject extends Model
{
    use HasCurriculumRoutes;

    /** @use HasFactory<SubjectFactory> */
    use HasFactory;

    protected $appends = ['route_parameters'];

    protected $fillable = ['program_id', 'name', 'code', 'description', 'sort_order'];

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function syllabusTopics(): HasMany
    {
        return $this->hasMany(SyllabusTopic::class);
    }

    public function topics(): HasMany
    {
        return $this->syllabusTopics();
    }

    public function concepts(): HasMany
    {
        return $this->hasMany(Concept::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(SubjectFile::class);
    }

    /** @return array<string, int> */
    public function curriculumRouteParameters(): array
    {
        return ['program' => (int) $this->program_id, 'subject' => (int) $this->id];
    }
}

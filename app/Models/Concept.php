<?php

namespace App\Models;

use App\Concerns\Models\HasCurriculumRoutes;
use Database\Factories\ConceptFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Concept extends Model
{
    use HasCurriculumRoutes;

    /** @use HasFactory<ConceptFactory> */
    use HasFactory;

    protected $appends = ['route_parameters'];

    protected $with = ['subject'];

    protected $fillable = ['subject_id', 'syllabus_topic_id', 'code', 'title', 'description', 'sort_order'];

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function syllabusTopic(): BelongsTo
    {
        return $this->belongsTo(SyllabusTopic::class);
    }

    public function prerequisites(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'concept_prerequisites', 'concept_id', 'prerequisite_concept_id')->withTimestamps();
    }

    public function dependents(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'concept_prerequisites', 'prerequisite_concept_id', 'concept_id')->withTimestamps();
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class)->orderBy('sort_order');
    }

    public function learningObjectives(): HasMany
    {
        return $this->hasMany(LearningObjective::class)->orderBy('sort_order');
    }

    public function objectives(): HasMany
    {
        return $this->learningObjectives();
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class)->orderBy('sort_order');
    }

    public function quizzes(): HasMany
    {
        return $this->hasMany(Quiz::class);
    }

    /** @return array<string, int> */
    public function curriculumRouteParameters(): array
    {
        return $this->subject->curriculumRouteParameters() + array_filter(['topic' => $this->syllabus_topic_id, 'concept' => (int) $this->id], fn (?int $id): bool => $id !== null);
    }
}

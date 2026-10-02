<?php

namespace App\Models;

use App\Concerns\Models\HasCurriculumRoutes;
use Database\Factories\QuizFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quiz extends Model
{
    use HasCurriculumRoutes;

    /** @use HasFactory<QuizFactory> */
    use HasFactory;

    protected $appends = ['route_parameters'];

    protected $with = ['concept'];

    protected $fillable = ['concept_id', 'title', 'description', 'passing_score'];

    protected function casts(): array
    {
        return ['passing_score' => 'decimal:2'];
    }

    public function concept(): BelongsTo
    {
        return $this->belongsTo(Concept::class);
    }

    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(Question::class, 'quiz_questions')->withPivot('sort_order')->orderBy('quiz_questions.sort_order');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }

    /** @return array<string, int> */
    public function curriculumRouteParameters(): array
    {
        return $this->concept->curriculumRouteParameters() + ['quiz' => (int) $this->id];
    }
}

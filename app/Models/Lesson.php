<?php

namespace App\Models;

use App\Concerns\Models\HasCurriculumRoutes;
use Database\Factories\LessonFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Lesson extends Model
{
    use HasCurriculumRoutes;

    /** @use HasFactory<LessonFactory> */
    use HasFactory;

    protected $appends = ['route_parameters'];

    protected $with = ['concept'];

    protected $fillable = ['concept_id', 'title', 'summary', 'content', 'sort_order'];

    public function concept(): BelongsTo
    {
        return $this->belongsTo(Concept::class);
    }

    /** @return array<string, int> */
    public function curriculumRouteParameters(): array
    {
        return $this->concept->curriculumRouteParameters() + ['lesson' => (int) $this->id];
    }
}

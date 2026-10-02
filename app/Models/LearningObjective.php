<?php

namespace App\Models;

use App\Concerns\Models\HasCurriculumRoutes;
use Database\Factories\LearningObjectiveFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LearningObjective extends Model
{
    use HasCurriculumRoutes;

    /** @use HasFactory<LearningObjectiveFactory> */
    use HasFactory;

    protected $appends = ['route_parameters'];

    protected $with = ['concept'];

    protected $fillable = ['concept_id', 'description', 'sort_order'];

    public function concept(): BelongsTo
    {
        return $this->belongsTo(Concept::class);
    }

    /** @return array<string, int> */
    public function curriculumRouteParameters(): array
    {
        return $this->concept->curriculumRouteParameters() + ['objective' => (int) $this->id];
    }
}

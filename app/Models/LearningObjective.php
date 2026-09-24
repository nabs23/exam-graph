<?php

namespace App\Models;

use Database\Factories\LearningObjectiveFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LearningObjective extends Model
{
    /** @use HasFactory<LearningObjectiveFactory> */
    use HasFactory;

    protected $fillable = ['concept_id', 'description', 'sort_order'];

    public function concept(): BelongsTo
    {
        return $this->belongsTo(Concept::class);
    }
}

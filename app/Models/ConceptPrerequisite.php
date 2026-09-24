<?php

namespace App\Models;

use Database\Factories\ConceptPrerequisiteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConceptPrerequisite extends Model
{
    /** @use HasFactory<ConceptPrerequisiteFactory> */
    use HasFactory;

    protected $fillable = ['concept_id', 'prerequisite_concept_id'];

    public function concept(): BelongsTo
    {
        return $this->belongsTo(Concept::class);
    }

    public function prerequisiteConcept(): BelongsTo
    {
        return $this->belongsTo(Concept::class, 'prerequisite_concept_id');
    }
}

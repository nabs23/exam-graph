<?php

namespace App\Models;

use Database\Factories\ConceptProgressFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConceptProgress extends Model
{
    /** @use HasFactory<ConceptProgressFactory> */
    use HasFactory;

    protected $table = 'concept_progress';

    protected $fillable = ['user_id', 'concept_id', 'last_score', 'best_score', 'attempts_count', 'last_attempted_at', 'is_completed'];

    protected function casts(): array
    {
        return ['last_score' => 'decimal:2', 'best_score' => 'decimal:2', 'last_attempted_at' => 'datetime', 'is_completed' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function concept(): BelongsTo
    {
        return $this->belongsTo(Concept::class);
    }
}

<?php

namespace App\Models;

use Database\Factories\LessonFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Lesson extends Model
{
    /** @use HasFactory<LessonFactory> */
    use HasFactory;

    protected $fillable = ['concept_id', 'title', 'summary', 'content', 'sort_order'];

    public function concept(): BelongsTo
    {
        return $this->belongsTo(Concept::class);
    }
}

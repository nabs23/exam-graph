<?php

namespace App\Models;

use Database\Factories\SyllabusTopicFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SyllabusTopic extends Model
{
    /** @use HasFactory<SyllabusTopicFactory> */
    use HasFactory;

    protected $fillable = ['subject_id', 'parent_id', 'code', 'title', 'description', 'sort_order'];

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    public function concepts(): HasMany
    {
        return $this->hasMany(Concept::class);
    }
}

<?php

namespace App\Models;

use App\Concerns\Models\HasCurriculumRoutes;
use Database\Factories\SyllabusTopicFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SyllabusTopic extends Model
{
    use HasCurriculumRoutes;

    /** @use HasFactory<SyllabusTopicFactory> */
    use HasFactory;

    protected $appends = ['route_parameters'];

    protected $with = ['subject'];

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

    /** @return array<string, int> */
    public function curriculumRouteParameters(): array
    {
        return $this->subject->curriculumRouteParameters() + ['topic' => (int) $this->id];
    }
}

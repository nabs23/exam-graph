<?php

namespace App\Models;

use Database\Factories\ProgramFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Program extends Model
{
    /** @use HasFactory<ProgramFactory> */
    use HasFactory;

    protected $fillable = ['name', 'code', 'description'];

    public function subjects(): HasMany
    {
        return $this->hasMany(Subject::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(ProgramFile::class);
    }

    public function curriculumExtractions(): HasMany
    {
        return $this->hasMany(CurriculumExtraction::class);
    }
}

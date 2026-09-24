<?php

namespace Database\Factories;

use App\Models\Concept;
use App\Models\ConceptProgress;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConceptProgress>
 */
class ConceptProgressFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'concept_id' => Concept::factory(),
            'last_score' => 80,
            'best_score' => 80,
            'attempts_count' => 1,
            'last_attempted_at' => now(),
            'is_completed' => true,
        ];
    }
}

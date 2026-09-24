<?php

namespace Database\Factories;

use App\Models\Concept;
use App\Models\LearningObjective;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LearningObjective>
 */
class LearningObjectiveFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'concept_id' => Concept::factory(),
            'description' => fake()->sentence(),
            'sort_order' => fake()->numberBetween(1, 10),
        ];
    }
}

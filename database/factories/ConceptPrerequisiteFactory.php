<?php

namespace Database\Factories;

use App\Models\Concept;
use App\Models\ConceptPrerequisite;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConceptPrerequisite>
 */
class ConceptPrerequisiteFactory extends Factory
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
            'prerequisite_concept_id' => Concept::factory(),
        ];
    }
}

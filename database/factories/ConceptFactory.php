<?php

namespace Database\Factories;

use App\Models\Concept;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Concept>
 */
class ConceptFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subject_id' => Subject::factory(),
            'code' => fake()->unique()->lexify('CON-???'),
            'title' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'sort_order' => fake()->numberBetween(1, 10),
        ];
    }
}

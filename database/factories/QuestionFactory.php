<?php

namespace Database\Factories;

use App\Models\Concept;
use App\Models\Question;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Question>
 */
class QuestionFactory extends Factory
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
            'prompt' => fake()->sentence().'?',
            'explanation' => fake()->paragraph(),
            'difficulty' => fake()->randomElement(['easy', 'medium', 'hard']),
            'sort_order' => fake()->numberBetween(1, 10),
        ];
    }
}

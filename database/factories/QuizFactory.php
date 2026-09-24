<?php

namespace Database\Factories;

use App\Models\Concept;
use App\Models\Quiz;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Quiz>
 */
class QuizFactory extends Factory
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
            'title' => fake()->sentence(3),
            'description' => fake()->sentence(),
            'passing_score' => 80,
        ];
    }
}

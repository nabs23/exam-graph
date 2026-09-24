<?php

namespace Database\Factories;

use App\Models\Question;
use App\Models\QuestionChoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuestionChoice>
 */
class QuestionChoiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'question_id' => Question::factory(),
            'content' => fake()->sentence(),
            'is_correct' => false,
            'sort_order' => fake()->numberBetween(1, 4),
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\AttemptAnswer;
use App\Models\Question;
use App\Models\QuestionChoice;
use App\Models\QuizAttempt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttemptAnswer>
 */
class AttemptAnswerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'quiz_attempt_id' => QuizAttempt::factory(),
            'question_id' => Question::factory(),
            'question_choice_id' => QuestionChoice::factory(),
            'is_correct' => false,
        ];
    }
}

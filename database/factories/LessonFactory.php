<?php

namespace Database\Factories;

use App\Models\Concept;
use App\Models\Lesson;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lesson>
 */
class LessonFactory extends Factory
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
            'title' => fake()->sentence(4),
            'summary' => fake()->sentence(),
            'content' => fake()->paragraphs(2, true),
            'sort_order' => fake()->numberBetween(1, 10),
        ];
    }
}

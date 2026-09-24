<?php

namespace Database\Factories;

use App\Models\Subject;
use App\Models\SyllabusTopic;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SyllabusTopic>
 */
class SyllabusTopicFactory extends Factory
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
            'code' => fake()->unique()->lexify('TOP-???'),
            'title' => fake()->sentence(3),
            'description' => fake()->sentence(),
            'sort_order' => fake()->numberBetween(1, 10),
        ];
    }
}

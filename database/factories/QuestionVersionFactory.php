<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Question;
use App\Models\QuestionVersion;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<QuestionVersion>
 */
class QuestionVersionFactory extends Factory
{
    protected $model = QuestionVersion::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'question_id' => Question::factory(),
            'version' => 1,
            'type' => 'mcq',
            'payload' => [
                'text' => fake()->sentence(8),
                'options' => [
                    'A' => fake()->word(),
                    'B' => fake()->word(),
                    'C' => fake()->word(),
                    'D' => fake()->word(),
                ],
            ],
            'answer_key' => ['correct' => 'A'],
            'rubric' => null,
        ];
    }
}

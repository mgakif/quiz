<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\QuestionGeneration;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<QuestionGeneration>
 */
class QuestionGenerationFactory extends Factory
{
    protected $model = QuestionGeneration::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'status' => 'pending',
            'model' => 'mock-model',
            'generated_count' => 0,
            'blueprint' => [
                'topics' => ['topic-1'],
                'learning_objectives' => ['lo-1'],
                'type_counts' => ['mcq' => 1, 'matching' => 0, 'short' => 0, 'essay' => 0],
                'difficulty_distribution' => ['easy' => 1, 'medium' => 0, 'hard' => 0],
            ],
            'raw_output' => null,
            'validation_errors' => null,
            'summary' => null,
            'created_by' => User::factory(),
        ];
    }
}

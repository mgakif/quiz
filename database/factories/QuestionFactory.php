<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Question;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Question>
 */
class QuestionFactory extends Factory
{
    protected $model = Question::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'status' => Question::STATUS_ACTIVE,
            'difficulty' => fake()->numberBetween(1, 5),
            'tags' => fake()->randomElements(['algebra', 'geometry', 'logic', 'history'], fake()->numberBetween(1, 3)),
            'created_by' => User::factory(),
        ];
    }
}

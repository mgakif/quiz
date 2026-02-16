<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Attempt;
use App\Models\AttemptItem;
use App\Models\Question;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Appeal>
 */
class AppealFactory extends Factory
{
    public function definition(): array
    {
        $student = User::factory()->student()->create();
        $question = Question::factory()->create();
        $version = $question->createVersion([
            'type' => 'essay',
            'payload' => ['text' => fake()->sentence()],
            'answer_key' => ['expected_points' => 10],
            'rubric' => ['criteria' => ['accuracy']],
        ]);
        $attempt = Attempt::query()->create([
            'exam_id' => fake()->numberBetween(1, 1000),
            'student_id' => $student->id,
            'grade_state' => 'released',
            'release_at' => now()->subDay(),
        ]);
        $attemptItem = AttemptItem::query()->create([
            'attempt_id' => $attempt->id,
            'question_version_id' => $version->id,
            'order' => 1,
            'max_points' => 10,
        ]);

        return [
            'uuid' => fake()->uuid(),
            'attempt_item_id' => $attemptItem->id,
            'student_id' => $student->id,
            'reason_text' => fake()->paragraph(),
            'status' => 'open',
            'teacher_note' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}

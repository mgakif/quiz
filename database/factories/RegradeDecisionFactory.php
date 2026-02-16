<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Attempt;
use App\Models\AttemptItem;
use App\Models\Question;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RegradeDecision>
 */
class RegradeDecisionFactory extends Factory
{
    public function definition(): array
    {
        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->student()->create();
        $question = Question::factory()->create();
        $version = $question->createVersion([
            'type' => 'mcq',
            'payload' => ['stem' => fake()->sentence()],
            'answer_key' => ['correct_choice_id' => 'A'],
            'rubric' => null,
        ]);
        $attempt = Attempt::query()->create([
            'exam_id' => fake()->numberBetween(1, 1000),
            'student_id' => $student->id,
            'grade_state' => 'graded',
        ]);
        $attemptItem = AttemptItem::query()->create([
            'attempt_id' => $attempt->id,
            'question_version_id' => $version->id,
            'order' => 1,
            'max_points' => 5,
        ]);

        return [
            'uuid' => fake()->uuid(),
            'scope' => 'attempt_item',
            'attempt_item_id' => $attemptItem->id,
            'question_version_id' => $version->id,
            'decision_type' => 'answer_key_change',
            'payload' => ['new_answer_key' => ['correct_choice_id' => 'B']],
            'decided_by' => $teacher->id,
            'decided_at' => now(),
            'created_at' => now(),
        ];
    }
}

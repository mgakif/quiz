<?php

declare(strict_types=1);

use App\Domain\Questions\Actions\EvaluateQuestionPreviewAnswer;
use App\Models\Question;
use App\Models\User;

it('grades mcq preview answer correctly', function (): void {
    $teacher = User::factory()->teacher()->create();
    $question = Question::factory()->create([
        'created_by' => $teacher->id,
    ]);

    $version = $question->createVersion([
        'type' => 'mcq',
        'payload' => [
            'stem' => 'Capital of France?',
            'choices' => [
                ['id' => 'A', 'text' => 'Paris'],
                ['id' => 'B', 'text' => 'Berlin'],
            ],
        ],
        'answer_key' => ['correct_choice_id' => 'A'],
        'rubric' => null,
    ]);

    $result = app(EvaluateQuestionPreviewAnswer::class)->execute($version, [
        'choice_id' => 'A',
    ]);

    expect($result['is_correct'])->toBeTrue()
        ->and($result['earned_points'])->toBe(1.0)
        ->and($result['max_points'])->toBe(1.0)
        ->and($result['feedback'])->toBe('Correct answer.');

    $wrongResult = app(EvaluateQuestionPreviewAnswer::class)->execute($version, [
        'choice_id' => 'B',
    ]);

    expect($wrongResult['is_correct'])->toBeFalse()
        ->and($wrongResult['earned_points'])->toBe(0.0)
        ->and($wrongResult['max_points'])->toBe(1.0)
        ->and($wrongResult['feedback'])->toBe('Incorrect answer.');
});

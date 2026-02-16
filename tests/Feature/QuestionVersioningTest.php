<?php

declare(strict_types=1);

use App\Models\Question;
use App\Models\User;

it('increments version when creating new version', function (): void {
    $teacher = User::factory()->create();

    $question = Question::factory()->create([
        'created_by' => $teacher->id,
    ]);

    $first = $question->createVersion([
        'type' => 'mcq',
        'payload' => ['text' => 'v1', 'options' => ['A' => '1', 'B' => '2']],
        'answer_key' => ['correct' => 'A'],
        'rubric' => null,
    ]);

    $second = $question->createVersion([
        'type' => 'mcq',
        'payload' => ['text' => 'v2', 'options' => ['A' => '3', 'B' => '4']],
        'answer_key' => ['correct' => 'B'],
        'rubric' => null,
    ]);

    expect($first->version)->toBe(1)
        ->and($second->version)->toBe(2)
        ->and($question->fresh()->latestVersion->id)->toBe($second->id);
});

it('excludes archived and deprecated questions from selection pool', function (): void {
    $active = Question::factory()->create(['status' => Question::STATUS_ACTIVE]);
    Question::factory()->create(['status' => Question::STATUS_ARCHIVED]);
    Question::factory()->create(['status' => Question::STATUS_DEPRECATED]);

    $selectableIds = Question::query()->selectable()->pluck('id');

    expect($selectableIds)->toHaveCount(1)
        ->and($selectableIds->first())->toBe($active->id);
});

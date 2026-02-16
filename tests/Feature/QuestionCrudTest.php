<?php

declare(strict_types=1);

use App\Models\Question;
use App\Models\QuestionStat;
use App\Models\User;

it('supports basic question CRUD flow with versions', function (): void {
    $teacher = User::factory()->teacher()->create();

    $question = Question::factory()->create([
        'created_by' => $teacher->id,
        'difficulty' => 2,
        'status' => Question::STATUS_ACTIVE,
        'tags' => ['algebra'],
    ]);

    $question->createVersion([
        'type' => 'mcq',
        'payload' => ['text' => 'Question v1', 'options' => ['A' => '1', 'B' => '2']],
        'answer_key' => ['correct' => 'A'],
        'rubric' => null,
    ]);

    $question->createVersion([
        'type' => 'mcq',
        'payload' => ['text' => 'Question v2', 'options' => ['A' => '3', 'B' => '4']],
        'answer_key' => ['correct' => 'B'],
        'rubric' => null,
    ]);

    QuestionStat::query()->create([
        'question_id' => $question->id,
        'usage_count' => 10,
        'correct_rate' => 70.50,
        'appeal_count' => 1,
    ]);

    expect($question->fresh()->latestVersion->version)->toBe(2);

    $question->update(['difficulty' => 4]);

    expect($question->fresh()->difficulty)->toBe(4);

    $question->delete();

    $this->assertDatabaseMissing('questions', ['id' => $question->id]);
    $this->assertDatabaseMissing('question_versions', ['question_id' => $question->id]);
    $this->assertDatabaseMissing('question_stats', ['question_id' => $question->id]);
});

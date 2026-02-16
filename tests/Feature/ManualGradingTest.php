<?php

declare(strict_types=1);

use App\Models\Attempt;
use App\Models\AttemptItem;
use App\Models\AttemptResponse;
use App\Models\Question;
use App\Models\User;
use App\Services\Grading\ManualGradingService;
use Illuminate\Validation\ValidationException;

it('saves rubric grading correctly for an open-ended response', function (): void {
    $teacher = User::factory()->teacher()->create();
    $student = User::factory()->student()->create();

    $question = Question::factory()->create();
    $version = $question->createVersion([
        'type' => 'essay',
        'payload' => ['text' => 'Explain photosynthesis.'],
        'answer_key' => ['guideline' => 'Mentions chlorophyll and glucose production.'],
        'rubric' => ['criteria' => ['accuracy', 'clarity']],
    ]);

    $attempt = Attempt::query()->create([
        'exam_id' => 1,
        'student_id' => $student->id,
        'started_at' => now()->subMinutes(10),
        'submitted_at' => now(),
        'grade_state' => 'in_review',
    ]);

    $item = AttemptItem::query()->create([
        'attempt_id' => $attempt->id,
        'question_version_id' => $version->id,
        'order' => 1,
        'max_points' => 10,
    ]);

    AttemptResponse::query()->create([
        'attempt_item_id' => $item->id,
        'response_payload' => ['text' => 'Photosynthesis converts light energy into chemical energy.'],
        'submitted_at' => now(),
    ]);

    $service = app(ManualGradingService::class);

    $service->gradeAttemptItem(
        attemptItem: $item,
        scores: [
            ['criterion' => 'accuracy', 'points' => 4.5],
            ['criterion' => 'clarity', 'points' => 3.0],
        ],
        totalPoints: 7.5,
        gradedBy: $teacher->id,
        overrideReason: null,
    );

    $this->assertDatabaseHas('rubric_scores', [
        'attempt_item_id' => $item->id,
        'graded_by' => $teacher->id,
        'total_points' => 7.50,
    ]);
});

it('requires override reason when total points change on regrade', function (): void {
    $teacher = User::factory()->teacher()->create();
    $student = User::factory()->student()->create();

    $question = Question::factory()->create();
    $version = $question->createVersion([
        'type' => 'short',
        'payload' => ['text' => 'What is gravity?'],
        'answer_key' => ['guideline' => 'Force attracting bodies toward each other.'],
        'rubric' => ['criteria' => ['concept']],
    ]);

    $attempt = Attempt::query()->create([
        'exam_id' => 1,
        'student_id' => $student->id,
        'started_at' => now()->subMinutes(5),
        'submitted_at' => now(),
        'grade_state' => 'in_review',
    ]);

    $item = AttemptItem::query()->create([
        'attempt_id' => $attempt->id,
        'question_version_id' => $version->id,
        'order' => 1,
        'max_points' => 5,
    ]);

    AttemptResponse::query()->create([
        'attempt_item_id' => $item->id,
        'response_payload' => ['text' => 'Gravity is a force.'],
        'submitted_at' => now(),
    ]);

    $service = app(ManualGradingService::class);

    $service->gradeAttemptItem(
        attemptItem: $item,
        scores: [
            ['criterion' => 'concept', 'points' => 3.0],
        ],
        totalPoints: 3.0,
        gradedBy: $teacher->id,
    );

    expect(fn () => $service->gradeAttemptItem(
        attemptItem: $item,
        scores: [
            ['criterion' => 'concept', 'points' => 4.0],
        ],
        totalPoints: 4.0,
        gradedBy: $teacher->id,
        overrideReason: null,
    ))->toThrow(ValidationException::class);

    $service->gradeAttemptItem(
        attemptItem: $item,
        scores: [
            ['criterion' => 'concept', 'points' => 4.0],
        ],
        totalPoints: 4.0,
        gradedBy: $teacher->id,
        overrideReason: 'Applied appeal decision after re-evaluation.',
    );

    $this->assertDatabaseHas('rubric_scores', [
        'attempt_item_id' => $item->id,
        'total_points' => 4.00,
        'override_reason' => 'Applied appeal decision after re-evaluation.',
    ]);
});

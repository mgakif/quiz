<?php

declare(strict_types=1);

use App\Jobs\ReleaseDueGradesJob;
use App\Models\Appeal;
use App\Models\AiGrading;
use App\Models\Attempt;
use App\Models\AttemptItem;
use App\Models\Question;
use App\Models\RubricScore;
use App\Models\User;

it('does not return score and feedback payload before release', function (): void {
    $student = User::factory()->student()->create();

    $question = Question::factory()->create();
    $version = $question->createVersion([
        'type' => 'essay',
        'payload' => ['text' => 'Explain photosynthesis.'],
        'answer_key' => ['guideline' => 'Mentions light conversion and glucose.'],
        'rubric' => ['criteria' => ['accuracy', 'clarity']],
    ]);

    $attempt = Attempt::query()->create([
        'exam_id' => 1,
        'student_id' => $student->id,
        'submitted_at' => now(),
        'grade_state' => 'graded',
        'release_at' => now()->addHour(),
    ]);

    $item = AttemptItem::query()->create([
        'attempt_id' => $attempt->id,
        'question_version_id' => $version->id,
        'order' => 1,
        'max_points' => 10,
    ]);

    RubricScore::query()->create([
        'attempt_item_id' => $item->id,
        'scores' => [
            ['criterion' => 'accuracy', 'points' => 4],
            ['criterion' => 'clarity', 'points' => 3],
        ],
        'total_points' => 7,
        'graded_by' => User::factory()->teacher()->create()->id,
        'graded_at' => now(),
    ]);

    AiGrading::query()->create([
        'attempt_item_id' => $item->id,
        'response_json' => ['feedback' => 'Strong attempt.'],
        'confidence' => 0.87,
        'flags' => [],
        'status' => 'approved',
    ]);

    Appeal::query()->create([
        'attempt_item_id' => $item->id,
        'student_id' => $student->id,
        'reason_text' => 'Please re-check criterion interpretation.',
        'status' => 'open',
    ]);

    $response = $this
        ->actingAs($student)
        ->getJson("/api/student/attempts/{$attempt->id}/results");

    $response
        ->assertSuccessful()
        ->assertJson([
            'attempt_id' => $attempt->id,
            'status' => 'graded',
            'grade_state' => 'graded',
            'release_at' => $attempt->release_at?->toIso8601String(),
            'submitted_at' => $attempt->submitted_at?->toIso8601String(),
        ])
        ->assertJsonPath('message', 'Notlar '.$attempt->release_at?->format('Y-m-d H:i').' tarihinde aciklanacak.')
        ->assertJsonMissingPath('total_points')
        ->assertJsonMissingPath('items')
        ->assertJsonMissingPath('feedback')
        ->assertJsonMissingPath('rubric_scores')
        ->assertJsonMissingPath('ai_gradings')
        ->assertJsonMissingPath('correct_answers')
        ->assertJsonMissingPath('reviewer_issues');
});

it('returns score and feedback payload after release', function (): void {
    $student = User::factory()->student()->create();
    $teacher = User::factory()->teacher()->create();

    $question = Question::factory()->create();
    $version = $question->createVersion([
        'type' => 'essay',
        'payload' => ['text' => 'Explain photosynthesis.'],
        'answer_key' => ['guideline' => 'Mentions light conversion and glucose.'],
        'rubric' => ['criteria' => ['accuracy', 'clarity']],
    ]);

    $attempt = Attempt::query()->create([
        'exam_id' => 1,
        'student_id' => $student->id,
        'submitted_at' => now(),
        'grade_state' => 'released',
        'release_at' => now()->subMinute(),
    ]);

    $item = AttemptItem::query()->create([
        'attempt_id' => $attempt->id,
        'question_version_id' => $version->id,
        'order' => 1,
        'max_points' => 10,
    ]);

    RubricScore::query()->create([
        'attempt_item_id' => $item->id,
        'scores' => [
            ['criterion' => 'accuracy', 'points' => 4],
            ['criterion' => 'clarity', 'points' => 3],
        ],
        'total_points' => 7,
        'graded_by' => $teacher->id,
        'graded_at' => now(),
    ]);

    AiGrading::query()->create([
        'attempt_item_id' => $item->id,
        'response_json' => ['feedback' => 'Strong attempt.'],
        'confidence' => 0.87,
        'flags' => [],
        'status' => 'approved',
    ]);

    Appeal::query()->create([
        'attempt_item_id' => $item->id,
        'student_id' => $student->id,
        'reason_text' => 'Please re-check criterion interpretation.',
        'status' => 'open',
    ]);

    $response = $this
        ->actingAs($student)
        ->getJson("/api/student/attempts/{$attempt->id}/results");

    $response
        ->assertSuccessful()
        ->assertJsonPath('attempt_id', $attempt->id)
        ->assertJsonPath('status', 'released')
        ->assertJsonPath('grade_state', 'released')
        ->assertJsonPath('release_at', $attempt->release_at?->toIso8601String())
        ->assertJsonPath('submitted_at', $attempt->submitted_at?->toIso8601String())
        ->assertJsonPath('total_points', 7)
        ->assertJsonPath('items.0.score', 7)
        ->assertJsonPath('items.0.feedback', 'Strong attempt.')
        ->assertJsonPath('items.0.rubric_scores.0.criterion', 'accuracy')
        ->assertJsonPath('items.0.ai_gradings.feedback', 'Strong attempt.')
        ->assertJsonPath('items.0.appeal_count', 1)
        ->assertJsonPath('items.0.appeals.0.status', 'open');
});

it('release due grades job releases only attempts that are due', function (): void {
    $student = User::factory()->student()->create();

    $dueAttempt = Attempt::query()->create([
        'exam_id' => 1,
        'student_id' => $student->id,
        'submitted_at' => now(),
        'grade_state' => 'graded',
        'release_at' => now()->subMinute(),
    ]);

    $futureAttempt = Attempt::query()->create([
        'exam_id' => 1,
        'student_id' => $student->id,
        'submitted_at' => now(),
        'grade_state' => 'graded',
        'release_at' => now()->addHour(),
    ]);

    $alreadyReleasedAttempt = Attempt::query()->create([
        'exam_id' => 1,
        'student_id' => $student->id,
        'submitted_at' => now(),
        'grade_state' => 'released',
        'release_at' => now()->subHour(),
    ]);

    (new ReleaseDueGradesJob())->handle();

    expect($dueAttempt->fresh()->grade_state)->toBe('released')
        ->and($futureAttempt->fresh()->grade_state)->toBe('graded')
        ->and($alreadyReleasedAttempt->fresh()->grade_state)->toBe('released');

    $this->assertDatabaseHas('audit_events', [
        'event_type' => 'grades_released',
        'entity_type' => 'attempt',
        'entity_id' => (string) $dueAttempt->id,
        'actor_type' => 'system',
    ]);

    $this->assertDatabaseMissing('audit_events', [
        'event_type' => 'grades_released',
        'entity_type' => 'attempt',
        'entity_id' => (string) $futureAttempt->id,
    ]);
});

<?php

declare(strict_types=1);

use App\Domain\Appeals\Actions\ResolveAppeal;
use App\Domain\Regrade\Actions\ApplyDecisionAndRegrade;
use App\Jobs\RegradeByQuestionVersionJob;
use App\Models\Appeal;
use App\Models\Assessment;
use App\Models\Attempt;
use App\Models\AttemptItem;
use App\Models\AttemptResponse;
use App\Models\Question;
use App\Models\RubricScore;
use App\Models\StudentTermGrade;
use App\Models\Term;
use App\Models\TermGradeScheme;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    config([
        'queue.default' => 'sync',
        'appeals.window_hours' => 72,
    ]);
});

it('allows students to appeal only their own attempt items', function (): void {
    $owner = User::factory()->student()->create();
    $otherStudent = User::factory()->student()->create();
    $question = Question::factory()->create();
    $version = $question->createVersion([
        'type' => 'essay',
        'payload' => ['text' => 'Explain inertia.'],
        'answer_key' => ['expected_points' => 10],
        'rubric' => ['criteria' => ['accuracy']],
    ]);

    $attempt = Attempt::query()->create([
        'exam_id' => 10,
        'student_id' => $owner->id,
        'grade_state' => 'released',
        'submitted_at' => now()->subHours(3),
        'release_at' => now()->subHours(2),
    ]);

    $item = AttemptItem::query()->create([
        'attempt_id' => $attempt->id,
        'question_version_id' => $version->id,
        'order' => 1,
        'max_points' => 10,
    ]);

    $this->actingAs($otherStudent)
        ->postJson("/api/student/attempt-items/{$item->id}/appeals", [
            'reason_text' => 'I think this score is incorrect.',
        ])
        ->assertForbidden();
});

it('does not allow appeal creation when grades are not released or the appeal window is closed', function (): void {
    $student = User::factory()->student()->create();
    $question = Question::factory()->create();
    $version = $question->createVersion([
        'type' => 'essay',
        'payload' => ['text' => 'Explain inertia.'],
        'answer_key' => ['expected_points' => 10],
        'rubric' => ['criteria' => ['accuracy']],
    ]);

    $notReleasedAttempt = Attempt::query()->create([
        'exam_id' => 11,
        'student_id' => $student->id,
        'grade_state' => 'graded',
        'submitted_at' => now()->subHours(3),
        'release_at' => now()->addHour(),
    ]);
    $notReleasedItem = AttemptItem::query()->create([
        'attempt_id' => $notReleasedAttempt->id,
        'question_version_id' => $version->id,
        'order' => 1,
        'max_points' => 10,
    ]);

    $closedWindowAttempt = Attempt::query()->create([
        'exam_id' => 12,
        'student_id' => $student->id,
        'grade_state' => 'released',
        'submitted_at' => now()->subDays(5),
        'release_at' => now()->subHours(73),
    ]);
    $closedWindowItem = AttemptItem::query()->create([
        'attempt_id' => $closedWindowAttempt->id,
        'question_version_id' => $version->id,
        'order' => 1,
        'max_points' => 10,
    ]);

    $this->actingAs($student)
        ->postJson("/api/student/attempt-items/{$notReleasedItem->id}/appeals", [
            'reason_text' => 'Please re-check this grading decision.',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('attempt_item_id');

    $this->actingAs($student)
        ->postJson("/api/student/attempt-items/{$closedWindowItem->id}/appeals", [
            'reason_text' => 'Please re-check this grading decision.',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('attempt_item_id');
});

it('creates regrade decision and audit event when teacher resolves an appeal', function (): void {
    Queue::fake();

    $teacher = User::factory()->teacher()->create();
    $student = User::factory()->student()->create();
    $question = Question::factory()->create();
    $version = $question->createVersion([
        'type' => 'essay',
        'payload' => ['text' => 'Explain inertia.'],
        'answer_key' => ['expected_points' => 10],
        'rubric' => ['criteria' => ['accuracy']],
    ]);
    $attempt = Attempt::query()->create([
        'exam_id' => 13,
        'student_id' => $student->id,
        'grade_state' => 'released',
        'submitted_at' => now()->subHours(4),
        'release_at' => now()->subHours(2),
    ]);
    $item = AttemptItem::query()->create([
        'attempt_id' => $attempt->id,
        'question_version_id' => $version->id,
        'order' => 1,
        'max_points' => 10,
    ]);
    $appeal = Appeal::query()->create([
        'attempt_item_id' => $item->id,
        'student_id' => $student->id,
        'reason_text' => 'Please reconsider.',
        'status' => Appeal::STATUS_OPEN,
    ]);

    app(ResolveAppeal::class)->execute(
        appeal: $appeal,
        teacher: $teacher,
        status: Appeal::STATUS_RESOLVED,
        teacherNote: 'Accepted.',
        decision: [
            'scope' => 'attempt_item',
            'decision_type' => 'partial_credit',
            'payload' => [
                'new_points' => 7,
                'reason' => 'Accepted alternative solution path.',
            ],
        ],
    );

    $this->assertDatabaseHas('regrade_decisions', [
        'attempt_item_id' => $item->id,
        'scope' => 'attempt_item',
        'decision_type' => 'partial_credit',
        'decided_by' => $teacher->id,
    ]);

    $this->assertDatabaseHas('audit_events', [
        'event_type' => 'appeal_resolved',
        'entity_type' => 'appeal',
    ]);
});

it('creates a new question version and dispatches regrade job on answer key change', function (): void {
    Queue::fake();

    $teacher = User::factory()->teacher()->create();
    $student = User::factory()->student()->create();
    $question = Question::factory()->create();
    $originalVersion = $question->createVersion([
        'type' => 'mcq',
        'payload' => ['stem' => '2 + 2 = ?'],
        'answer_key' => ['correct_choice_id' => 'A'],
        'rubric' => null,
    ]);
    $attempt = Attempt::query()->create([
        'exam_id' => 14,
        'student_id' => $student->id,
        'grade_state' => 'graded',
        'submitted_at' => now()->subHours(2),
    ]);
    $item = AttemptItem::query()->create([
        'attempt_id' => $attempt->id,
        'question_version_id' => $originalVersion->id,
        'order' => 1,
        'max_points' => 5,
    ]);
    AttemptResponse::query()->create([
        'attempt_item_id' => $item->id,
        'response_payload' => ['choice_id' => 'B'],
        'submitted_at' => now(),
    ]);

    $decision = app(ApplyDecisionAndRegrade::class)->execute(
        teacher: $teacher,
        scope: 'question_version',
        decisionType: 'answer_key_change',
        payload: [
            'new_answer_key' => ['correct_choice_id' => 'B'],
        ],
        questionVersion: $originalVersion,
    );

    expect($question->versions()->count())->toBe(2)
        ->and(data_get($decision->payload, 'new_version_id'))->not->toBeNull();

    Queue::assertPushed(RegradeByQuestionVersionJob::class, function (RegradeByQuestionVersionJob $job) use ($decision, $originalVersion): bool {
        return $job->regradeDecisionId === $decision->id
            && $job->questionVersionId === $originalVersion->id;
    });
});

it('requires reason for partial credit and writes manual override audit', function (): void {
    $teacher = User::factory()->teacher()->create();
    $student = User::factory()->student()->create();
    $question = Question::factory()->create();
    $version = $question->createVersion([
        'type' => 'essay',
        'payload' => ['text' => 'Explain inertia.'],
        'answer_key' => ['expected_points' => 10],
        'rubric' => ['criteria' => ['accuracy']],
    ]);
    $attempt = Attempt::query()->create([
        'exam_id' => 15,
        'student_id' => $student->id,
        'grade_state' => 'graded',
        'submitted_at' => now()->subHours(2),
    ]);
    $item = AttemptItem::query()->create([
        'attempt_id' => $attempt->id,
        'question_version_id' => $version->id,
        'order' => 1,
        'max_points' => 10,
    ]);
    RubricScore::query()->create([
        'attempt_item_id' => $item->id,
        'scores' => [['criterion' => 'accuracy', 'points' => 2]],
        'total_points' => 2,
        'graded_by' => $teacher->id,
        'graded_at' => now(),
        'is_draft' => false,
    ]);

    expect(fn () => app(ApplyDecisionAndRegrade::class)->execute(
        teacher: $teacher,
        scope: 'attempt_item',
        decisionType: 'partial_credit',
        payload: ['new_points' => 6],
        attemptItem: $item,
    ))->toThrow(ValidationException::class);

    app(ApplyDecisionAndRegrade::class)->execute(
        teacher: $teacher,
        scope: 'attempt_item',
        decisionType: 'partial_credit',
        payload: [
            'new_points' => 6,
            'reason' => 'Student argument is valid.',
        ],
        attemptItem: $item,
    );

    expect((float) $item->fresh()->rubricScore?->total_points)->toBe(6.0);

    $this->assertDatabaseHas('audit_events', [
        'event_type' => 'manual_override',
        'entity_type' => 'attempt_item',
        'entity_id' => (string) $item->id,
    ]);
});

it('recomputes student term grade after partial credit regrade', function (): void {
    $teacher = User::factory()->teacher()->create();
    $student = User::factory()->student()->create();

    $term = Term::query()->create([
        'name' => '2025-2026 Spring',
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
        'is_active' => true,
    ]);

    TermGradeScheme::query()->create([
        'term_id' => $term->id,
        'weights' => [
            'quiz' => 0.2,
            'exam' => 0.8,
            'assignment' => 0.0,
            'participation' => 0.0,
        ],
        'normalize_strategy' => 'use_scheme_only',
    ]);

    Assessment::query()->create([
        'term_id' => $term->id,
        'legacy_exam_id' => 9101,
        'class_id' => 9,
        'title' => 'Essay Regrade',
        'category' => 'exam',
        'weight' => 1.0,
        'published' => true,
    ]);

    Assessment::query()->create([
        'term_id' => $term->id,
        'legacy_exam_id' => 9102,
        'class_id' => 9,
        'title' => 'Quiz Stable',
        'category' => 'quiz',
        'weight' => 1.0,
        'published' => true,
    ]);

    $question = Question::factory()->create();
    $version = $question->createVersion([
        'type' => 'essay',
        'payload' => ['text' => 'Explain inertia.'],
        'answer_key' => ['expected_points' => 10],
        'rubric' => ['criteria' => ['accuracy']],
    ]);

    $attempt = Attempt::query()->create([
        'exam_id' => 9101,
        'student_id' => $student->id,
        'grade_state' => 'released',
        'started_at' => now()->subHours(4),
        'submitted_at' => now()->subHours(3),
        'release_at' => now()->subHours(2),
    ]);

    $item = AttemptItem::query()->create([
        'attempt_id' => $attempt->id,
        'question_version_id' => $version->id,
        'order' => 1,
        'max_points' => 10,
    ]);

    AttemptResponse::query()->create([
        'attempt_item_id' => $item->id,
        'response_payload' => ['text' => 'Initial answer'],
        'submitted_at' => now()->subHours(3),
    ]);

    RubricScore::query()->create([
        'attempt_item_id' => $item->id,
        'scores' => [['criterion' => 'accuracy', 'points' => 2]],
        'total_points' => 2,
        'graded_by' => $teacher->id,
        'graded_at' => now()->subHours(2),
        'is_draft' => false,
    ]);

    $quizQuestion = Question::factory()->create();
    $quizVersion = $quizQuestion->createVersion([
        'type' => 'mcq',
        'payload' => [
            'stem' => '2 + 2 = ?',
            'choices' => [
                ['id' => 'A', 'text' => '4'],
                ['id' => 'B', 'text' => '5'],
            ],
        ],
        'answer_key' => ['correct_choice_id' => 'A'],
        'rubric' => null,
    ]);

    $quizAttempt = Attempt::query()->create([
        'exam_id' => 9102,
        'student_id' => $student->id,
        'grade_state' => 'released',
        'started_at' => now()->subHours(4),
        'submitted_at' => now()->subHours(3),
        'release_at' => now()->subHours(2),
    ]);

    $quizItem = AttemptItem::query()->create([
        'attempt_id' => $quizAttempt->id,
        'question_version_id' => $quizVersion->id,
        'order' => 1,
        'max_points' => 10,
    ]);

    AttemptResponse::query()->create([
        'attempt_item_id' => $quizItem->id,
        'response_payload' => ['choice_id' => 'A'],
        'submitted_at' => now()->subHours(3),
    ]);

    app(\App\Domain\Gradebook\ComputeStudentTermGrade::class)->execute($term, $student);

    expect((float) StudentTermGrade::query()
        ->where('term_id', $term->id)
        ->where('student_id', $student->id)
        ->value('computed_grade'))->toBe(36.0);

    app(ApplyDecisionAndRegrade::class)->execute(
        teacher: $teacher,
        scope: 'attempt_item',
        decisionType: 'partial_credit',
        payload: [
            'new_points' => 8,
            'reason' => 'Accepted alternative solution path.',
        ],
        attemptItem: $item,
    );

    expect((float) StudentTermGrade::query()
        ->where('term_id', $term->id)
        ->where('student_id', $student->id)
        ->value('computed_grade'))->toBe(84.0);
});

it('applies void question drop from total deterministically', function (): void {
    $teacher = User::factory()->teacher()->create();
    $studentA = User::factory()->student()->create();
    $studentB = User::factory()->student()->create();
    $question = Question::factory()->create();
    $version = $question->createVersion([
        'type' => 'mcq',
        'payload' => ['stem' => 'Faulty question'],
        'answer_key' => ['correct_choice_id' => 'A'],
        'rubric' => null,
    ]);
    $attemptA = Attempt::query()->create([
        'exam_id' => 16,
        'student_id' => $studentA->id,
        'grade_state' => 'graded',
        'submitted_at' => now()->subHours(2),
    ]);
    $attemptB = Attempt::query()->create([
        'exam_id' => 16,
        'student_id' => $studentB->id,
        'grade_state' => 'graded',
        'submitted_at' => now()->subHours(2),
    ]);
    $itemA = AttemptItem::query()->create([
        'attempt_id' => $attemptA->id,
        'question_version_id' => $version->id,
        'order' => 1,
        'max_points' => 6,
    ]);
    $itemB = AttemptItem::query()->create([
        'attempt_id' => $attemptB->id,
        'question_version_id' => $version->id,
        'order' => 1,
        'max_points' => 6,
    ]);
    RubricScore::query()->create([
        'attempt_item_id' => $itemA->id,
        'scores' => [['criterion' => 'auto_grade', 'points' => 6]],
        'total_points' => 6,
        'graded_by' => $teacher->id,
        'graded_at' => now(),
        'is_draft' => false,
    ]);
    RubricScore::query()->create([
        'attempt_item_id' => $itemB->id,
        'scores' => [['criterion' => 'auto_grade', 'points' => 4]],
        'total_points' => 4,
        'graded_by' => $teacher->id,
        'graded_at' => now(),
        'is_draft' => false,
    ]);

    app(ApplyDecisionAndRegrade::class)->execute(
        teacher: $teacher,
        scope: 'question_version',
        decisionType: 'void_question',
        payload: ['mode' => 'drop_from_total'],
        questionVersion: $version,
    );

    expect((float) $itemA->fresh()->max_points)->toBe(0.0)
        ->and((float) $itemB->fresh()->max_points)->toBe(0.0)
        ->and((float) $itemA->fresh()->rubricScore?->total_points)->toBe(0.0)
        ->and((float) $itemB->fresh()->rubricScore?->total_points)->toBe(0.0);
});

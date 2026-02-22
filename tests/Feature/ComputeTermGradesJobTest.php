<?php

declare(strict_types=1);

use App\Jobs\ComputeStudentTermGradeJob;
use App\Jobs\ComputeTermGradesJob;
use App\Models\Assessment;
use App\Models\Attempt;
use App\Models\AttemptItem;
use App\Models\AttemptResponse;
use App\Models\AuditEvent;
use App\Models\Question;
use App\Models\Term;
use App\Models\TermGradeScheme;
use App\Models\User;

it('compute term grades job writes computed grades', function (): void {
    $teacher = User::factory()->teacher()->create();
    $student = User::factory()->student()->create();

    $term = Term::query()->create([
        'name' => '2025-2026 Guz',
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
        'is_active' => true,
    ]);

    $assessment = Assessment::query()->create([
        'term_id' => $term->id,
        'legacy_exam_id' => 3101,
        'class_id' => 10,
        'title' => 'Exam 1',
        'category' => 'exam',
        'weight' => 1.0,
        'scheduled_at' => now()->subDay(),
        'published' => true,
    ]);

    $question = Question::query()->create([
        'status' => Question::STATUS_ACTIVE,
        'difficulty' => 2,
        'tags' => ['gradebook-job-test'],
        'created_by' => $teacher->id,
    ]);

    $version = $question->createVersion([
        'type' => 'mcq',
        'payload' => [
            'stem' => '2+2',
            'choices' => [
                ['id' => 'A', 'text' => '4'],
                ['id' => 'B', 'text' => '5'],
            ],
        ],
        'answer_key' => ['correct_choice_id' => 'A'],
        'rubric' => null,
    ]);

    $attempt = Attempt::query()->create([
        'exam_id' => (int) $assessment->legacy_exam_id,
        'student_id' => $student->id,
        'started_at' => now()->subHours(3),
        'submitted_at' => now()->subHours(2),
        'grade_state' => 'released',
        'release_at' => now()->subHour(),
    ]);

    $item = AttemptItem::query()->create([
        'attempt_id' => $attempt->id,
        'question_version_id' => $version->id,
        'order' => 1,
        'max_points' => 10,
    ]);

    AttemptResponse::query()->create([
        'attempt_item_id' => $item->id,
        'response_payload' => ['choice_id' => 'A'],
        'submitted_at' => now()->subHours(2),
    ]);

    dispatch_sync(new ComputeTermGradesJob($term->id, 10));

    $this->assertDatabaseHas('student_term_grades', [
        'term_id' => $term->id,
        'student_id' => $student->id,
        'computed_grade' => 100.0,
    ]);
});

it('compute student term grade job is idempotent for audit events', function (): void {
    $teacher = User::factory()->teacher()->create();
    $student = User::factory()->student()->create();

    $term = Term::query()->create([
        'name' => '2025-2026 Guz',
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
        'is_active' => true,
    ]);

    $assessment = Assessment::query()->create([
        'term_id' => $term->id,
        'legacy_exam_id' => 3201,
        'class_id' => 10,
        'title' => 'Exam 2',
        'category' => 'exam',
        'weight' => 1.0,
        'scheduled_at' => now()->subDay(),
        'published' => true,
    ]);

    $question = Question::query()->create([
        'status' => Question::STATUS_ACTIVE,
        'difficulty' => 2,
        'tags' => ['gradebook-job-test'],
        'created_by' => $teacher->id,
    ]);

    $version = $question->createVersion([
        'type' => 'mcq',
        'payload' => [
            'stem' => '2+2',
            'choices' => [
                ['id' => 'A', 'text' => '4'],
                ['id' => 'B', 'text' => '5'],
            ],
        ],
        'answer_key' => ['correct_choice_id' => 'A'],
        'rubric' => null,
    ]);

    $attempt = Attempt::query()->create([
        'exam_id' => (int) $assessment->legacy_exam_id,
        'student_id' => $student->id,
        'started_at' => now()->subHours(3),
        'submitted_at' => now()->subHours(2),
        'grade_state' => 'released',
        'release_at' => now()->subHour(),
    ]);

    $item = AttemptItem::query()->create([
        'attempt_id' => $attempt->id,
        'question_version_id' => $version->id,
        'order' => 1,
        'max_points' => 10,
    ]);

    AttemptResponse::query()->create([
        'attempt_item_id' => $item->id,
        'response_payload' => ['choice_id' => 'A'],
        'submitted_at' => now()->subHours(2),
    ]);

    dispatch_sync(new ComputeStudentTermGradeJob($term->id, (int) $student->id));
    dispatch_sync(new ComputeStudentTermGradeJob($term->id, (int) $student->id));

    expect(
        AuditEvent::query()
            ->where('event_type', 'term_grade_computed')
            ->where('entity_type', 'student_term_grade')
            ->where('entity_id', sprintf('%s:%d', $term->id, $student->id))
            ->count()
    )->toBe(1);
});

it('compute student term grade job respects configured term grade scheme', function (): void {
    $teacher = User::factory()->teacher()->create();
    $student = User::factory()->student()->create();

    $term = Term::query()->create([
        'name' => '2025-2026 Guz',
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
        'legacy_exam_id' => 3211,
        'class_id' => 10,
        'title' => 'Quiz',
        'category' => 'quiz',
        'weight' => 10.0,
        'scheduled_at' => now()->subDay(),
        'published' => true,
    ]);

    Assessment::query()->create([
        'term_id' => $term->id,
        'legacy_exam_id' => 3212,
        'class_id' => 10,
        'title' => 'Exam',
        'category' => 'exam',
        'weight' => 0.1,
        'scheduled_at' => now()->subDay(),
        'published' => true,
    ]);

    $question = Question::query()->create([
        'status' => Question::STATUS_ACTIVE,
        'difficulty' => 2,
        'tags' => ['gradebook-job-test'],
        'created_by' => $teacher->id,
    ]);

    $quizVersion = $question->createVersion([
        'type' => 'mcq',
        'payload' => [
            'stem' => 'Q1',
            'choices' => [
                ['id' => 'A', 'text' => 'A'],
                ['id' => 'B', 'text' => 'B'],
            ],
        ],
        'answer_key' => ['correct_choice_id' => 'A'],
        'rubric' => null,
    ]);

    $examVersion = $question->createVersion([
        'type' => 'mcq',
        'payload' => [
            'stem' => 'Q2',
            'choices' => [
                ['id' => 'A', 'text' => 'A'],
                ['id' => 'B', 'text' => 'B'],
            ],
        ],
        'answer_key' => ['correct_choice_id' => 'B'],
        'rubric' => null,
    ]);

    $quizAttempt = Attempt::query()->create([
        'exam_id' => 3211,
        'student_id' => $student->id,
        'started_at' => now()->subHours(3),
        'submitted_at' => now()->subHours(2),
        'grade_state' => 'released',
        'release_at' => now()->subHour(),
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
        'submitted_at' => now()->subHours(2),
    ]);

    $examAttempt = Attempt::query()->create([
        'exam_id' => 3212,
        'student_id' => $student->id,
        'started_at' => now()->subHours(3),
        'submitted_at' => now()->subHours(2),
        'grade_state' => 'released',
        'release_at' => now()->subHour(),
    ]);

    $examItem = AttemptItem::query()->create([
        'attempt_id' => $examAttempt->id,
        'question_version_id' => $examVersion->id,
        'order' => 1,
        'max_points' => 10,
    ]);

    AttemptResponse::query()->create([
        'attempt_item_id' => $examItem->id,
        'response_payload' => ['choice_id' => 'A'],
        'submitted_at' => now()->subHours(2),
    ]);

    dispatch_sync(new ComputeStudentTermGradeJob($term->id, (int) $student->id));

    $this->assertDatabaseHas('student_term_grades', [
        'term_id' => $term->id,
        'student_id' => $student->id,
        'computed_grade' => 20.0,
    ]);
});

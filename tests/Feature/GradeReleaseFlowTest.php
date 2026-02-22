<?php

declare(strict_types=1);

use App\Jobs\ComputeStudentTermGradeJob;
use App\Jobs\ReleaseDueGradesJob;
use App\Models\AiGrading;
use App\Models\Appeal;
use App\Models\Assessment;
use App\Models\Attempt;
use App\Models\AttemptItem;
use App\Models\Question;
use App\Models\RubricScore;
use App\Models\StudentTermGrade;
use App\Models\Term;
use App\Models\TermGradeScheme;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

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

    (new ReleaseDueGradesJob)->handle();

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

it('recomputes student term grade when due attempt is released', function (): void {
    $teacher = User::factory()->teacher()->create();
    $student = User::factory()->student()->create();

    $term = Term::query()->create([
        'name' => '2025-2026 Spring',
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
        'is_active' => true,
    ]);

    Assessment::query()->create([
        'term_id' => $term->id,
        'legacy_exam_id' => 8101,
        'class_id' => 8,
        'title' => 'Quiz R1',
        'category' => 'quiz',
        'weight' => 1.0,
        'published' => true,
        'scheduled_at' => now()->subDay(),
    ]);

    $question = Question::factory()->create([
        'created_by' => $teacher->id,
    ]);

    $version = $question->createVersion([
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

    $attempt = Attempt::query()->create([
        'exam_id' => 8101,
        'student_id' => $student->id,
        'started_at' => now()->subHours(2),
        'submitted_at' => now()->subHour(),
        'grade_state' => 'graded',
        'release_at' => now()->subMinute(),
    ]);

    $item = AttemptItem::query()->create([
        'attempt_id' => $attempt->id,
        'question_version_id' => $version->id,
        'order' => 1,
        'max_points' => 10,
    ]);

    \App\Models\AttemptResponse::query()->create([
        'attempt_item_id' => $item->id,
        'response_payload' => ['choice_id' => 'A'],
        'submitted_at' => now()->subHour(),
    ]);

    (new ReleaseDueGradesJob)->handle();

    expect((float) StudentTermGrade::query()
        ->where('term_id', $term->id)
        ->where('student_id', $student->id)
        ->value('computed_grade'))->toBe(100.0);
});

it('dispatches a single student-term recompute for multiple released attempts of same student', function (): void {
    Queue::fake();

    $student = User::factory()->student()->create();

    $term = Term::query()->create([
        'name' => '2025-2026 Spring',
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
        'is_active' => true,
    ]);

    Assessment::query()->create([
        'term_id' => $term->id,
        'legacy_exam_id' => 8102,
        'class_id' => 8,
        'title' => 'Quiz R2',
        'category' => 'quiz',
        'weight' => 1.0,
        'published' => true,
    ]);

    Attempt::query()->create([
        'exam_id' => 8102,
        'student_id' => $student->id,
        'submitted_at' => now()->subHours(2),
        'grade_state' => 'graded',
        'release_at' => now()->subMinute(),
    ]);

    Attempt::query()->create([
        'exam_id' => 8102,
        'student_id' => $student->id,
        'submitted_at' => now()->subHour(),
        'grade_state' => 'graded',
        'release_at' => now()->subMinute(),
    ]);

    (new ReleaseDueGradesJob)->handle();

    Queue::assertPushed(ComputeStudentTermGradeJob::class, 1);
    Queue::assertPushed(ComputeStudentTermGradeJob::class, fn (ComputeStudentTermGradeJob $job): bool => $job->termId === $term->id && $job->studentId === $student->id);
});

it('applies term grade scheme during release-driven recompute', function (): void {
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
        'legacy_exam_id' => 8110,
        'class_id' => 8,
        'title' => 'Release Quiz',
        'category' => 'quiz',
        'weight' => 5.0,
        'published' => true,
    ]);

    Assessment::query()->create([
        'term_id' => $term->id,
        'legacy_exam_id' => 8111,
        'class_id' => 8,
        'title' => 'Release Exam',
        'category' => 'exam',
        'weight' => 0.1,
        'published' => true,
    ]);

    $quizQuestion = Question::factory()->create([
        'created_by' => $teacher->id,
    ]);
    $quizVersion = $quizQuestion->createVersion([
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

    $examQuestion = Question::factory()->create([
        'created_by' => $teacher->id,
    ]);
    $examVersion = $examQuestion->createVersion([
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
        'exam_id' => 8110,
        'student_id' => $student->id,
        'started_at' => now()->subHours(2),
        'submitted_at' => now()->subHour(),
        'grade_state' => 'graded',
        'release_at' => now()->subMinute(),
    ]);

    $quizItem = AttemptItem::query()->create([
        'attempt_id' => $quizAttempt->id,
        'question_version_id' => $quizVersion->id,
        'order' => 1,
        'max_points' => 10,
    ]);

    \App\Models\AttemptResponse::query()->create([
        'attempt_item_id' => $quizItem->id,
        'response_payload' => ['choice_id' => 'A'],
        'submitted_at' => now()->subHour(),
    ]);

    $examAttempt = Attempt::query()->create([
        'exam_id' => 8111,
        'student_id' => $student->id,
        'started_at' => now()->subHours(2),
        'submitted_at' => now()->subHour(),
        'grade_state' => 'graded',
        'release_at' => now()->subMinute(),
    ]);

    $examItem = AttemptItem::query()->create([
        'attempt_id' => $examAttempt->id,
        'question_version_id' => $examVersion->id,
        'order' => 1,
        'max_points' => 10,
    ]);

    \App\Models\AttemptResponse::query()->create([
        'attempt_item_id' => $examItem->id,
        'response_payload' => ['choice_id' => 'A'],
        'submitted_at' => now()->subHour(),
    ]);

    (new ReleaseDueGradesJob)->handle();

    expect((float) StudentTermGrade::query()
        ->where('term_id', $term->id)
        ->where('student_id', $student->id)
        ->value('computed_grade'))->toBe(20.0);
});

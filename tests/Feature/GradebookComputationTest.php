<?php

declare(strict_types=1);

use App\Domain\Gradebook\ComputeStudentTermGrade;
use App\Domain\Gradebook\OverrideStudentTermGrade;
use App\Models\Assessment;
use App\Models\Attempt;
use App\Models\AttemptItem;
use App\Models\AttemptResponse;
use App\Models\Question;
use App\Models\StudentTermGrade;
use App\Models\Term;
use App\Models\User;
use Illuminate\Validation\ValidationException;

it('does not include unreleased attempts in term grade computation', function (): void {
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
        'legacy_exam_id' => 3001,
        'class_id' => 9,
        'title' => 'Quiz 1',
        'category' => 'quiz',
        'weight' => 1.0,
        'scheduled_at' => now()->subDay(),
        'published' => true,
    ]);

    $version = createGradebookMcqVersion($teacher, 'A');

    createGradebookAttempt(
        student: $student,
        examId: (int) $assessment->legacy_exam_id,
        versionId: $version->id,
        choiceId: 'A',
        gradeState: 'graded',
        releaseAt: now()->addDay(),
    );

    $result = app(ComputeStudentTermGrade::class)->execute($term, $student, 9, false);

    expect($result['computed_grade'])->toBe(0.0)
        ->and($result['missing_assessments_count'])->toBe(1)
        ->and($result['assessments'][0]['attempt_status'])->toBe('unreleased');
});

it('computes weighted average from released attempts', function (): void {
    $teacher = User::factory()->teacher()->create();
    $student = User::factory()->student()->create();

    $term = Term::query()->create([
        'name' => '2025-2026 Guz',
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
        'is_active' => true,
    ]);

    $a1 = Assessment::query()->create([
        'term_id' => $term->id,
        'legacy_exam_id' => 3002,
        'class_id' => 9,
        'title' => 'Quiz A',
        'category' => 'quiz',
        'weight' => 2.0,
        'scheduled_at' => now()->subDays(3),
        'published' => true,
    ]);

    $a2 = Assessment::query()->create([
        'term_id' => $term->id,
        'legacy_exam_id' => 3003,
        'class_id' => 9,
        'title' => 'Quiz B',
        'category' => 'quiz',
        'weight' => 1.0,
        'scheduled_at' => now()->subDays(2),
        'published' => true,
    ]);

    $version = createGradebookMcqVersion($teacher, 'A');

    createGradebookAttempt($student, (int) $a1->legacy_exam_id, $version->id, 'A', 'released', now()->subHours(2));
    createGradebookAttempt($student, (int) $a2->legacy_exam_id, $version->id, 'B', 'released', now()->subHours(2));

    $result = app(ComputeStudentTermGrade::class)->execute($term, $student, 9, true);

    expect($result['computed_grade'])->toBe(66.67)
        ->and($result['missing_assessments_count'])->toBe(0);

    $this->assertDatabaseHas('student_term_grades', [
        'term_id' => $term->id,
        'student_id' => $student->id,
        'computed_grade' => 66.67,
    ]);
});

it('treats missing attempts as zero contribution and counts missing assessments', function (): void {
    $teacher = User::factory()->teacher()->create();
    $student = User::factory()->student()->create();

    $term = Term::query()->create([
        'name' => '2025-2026 Guz',
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
        'is_active' => true,
    ]);

    $done = Assessment::query()->create([
        'term_id' => $term->id,
        'legacy_exam_id' => 3004,
        'class_id' => 9,
        'title' => 'Done Quiz',
        'category' => 'quiz',
        'weight' => 1.0,
        'scheduled_at' => now()->subDays(2),
        'published' => true,
    ]);

    Assessment::query()->create([
        'term_id' => $term->id,
        'legacy_exam_id' => 3005,
        'class_id' => 9,
        'title' => 'Missing Quiz',
        'category' => 'quiz',
        'weight' => 1.0,
        'scheduled_at' => now()->subDay(),
        'published' => true,
    ]);

    $version = createGradebookMcqVersion($teacher, 'A');
    createGradebookAttempt($student, (int) $done->legacy_exam_id, $version->id, 'A', 'released', now()->subHours(2));

    $result = app(ComputeStudentTermGrade::class)->execute($term, $student, 9, false);

    expect($result['computed_grade'])->toBe(50.0)
        ->and($result['missing_assessments_count'])->toBe(1);
});

it('requires reason for override and writes audit event', function (): void {
    $teacher = User::factory()->teacher()->create();
    $student = User::factory()->student()->create();

    $term = Term::query()->create([
        'name' => '2025-2026 Guz',
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
        'is_active' => true,
    ]);

    $grade = StudentTermGrade::query()->create([
        'term_id' => $term->id,
        'student_id' => $student->id,
        'computed_grade' => 70.0,
        'computed_at' => now(),
    ]);

    expect(fn () => app(OverrideStudentTermGrade::class)->execute(
        studentTermGrade: $grade,
        teacher: $teacher,
        overriddenGrade: 85.0,
        reason: '',
    ))->toThrow(ValidationException::class);

    app(OverrideStudentTermGrade::class)->execute(
        studentTermGrade: $grade,
        teacher: $teacher,
        overriddenGrade: 85.0,
        reason: 'Manual correction after review.',
    );

    $this->assertDatabaseHas('student_term_grades', [
        'id' => $grade->id,
        'overridden_grade' => 85.0,
        'override_reason' => 'Manual correction after review.',
    ]);

    $this->assertDatabaseHas('audit_events', [
        'event_type' => 'term_grade_overridden',
        'entity_type' => 'student_term_grade',
        'actor_id' => $teacher->id,
    ]);
});

function createGradebookMcqVersion(User $teacher, string $correctChoiceId): \App\Models\QuestionVersion
{
    $question = Question::query()->create([
        'status' => Question::STATUS_ACTIVE,
        'difficulty' => 2,
        'tags' => ['gradebook-test'],
        'created_by' => $teacher->id,
    ]);

    return $question->createVersion([
        'type' => 'mcq',
        'payload' => [
            'stem' => 'Q',
            'choices' => [
                ['id' => 'A', 'text' => 'A'],
                ['id' => 'B', 'text' => 'B'],
            ],
        ],
        'answer_key' => ['correct_choice_id' => $correctChoiceId],
        'rubric' => null,
    ]);
}

function createGradebookAttempt(
    User $student,
    int $examId,
    int $versionId,
    string $choiceId,
    string $gradeState,
    \Carbon\CarbonImmutable|\Carbon\Carbon $releaseAt,
): void {
    $attempt = Attempt::query()->create([
        'exam_id' => $examId,
        'student_id' => $student->id,
        'started_at' => now()->subHours(3),
        'submitted_at' => now()->subHours(2),
        'grade_state' => $gradeState,
        'release_at' => $releaseAt,
    ]);

    $item = AttemptItem::query()->create([
        'attempt_id' => $attempt->id,
        'question_version_id' => $versionId,
        'order' => 1,
        'max_points' => 10,
    ]);

    AttemptResponse::query()->create([
        'attempt_item_id' => $item->id,
        'response_payload' => ['choice_id' => $choiceId],
        'submitted_at' => now()->subHours(2),
    ]);
}

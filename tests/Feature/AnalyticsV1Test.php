<?php

declare(strict_types=1);

use App\Domain\Analytics\Reports\ClassWeaknessReport;
use App\Domain\Analytics\Reports\StudentWeaknessReport;
use App\Jobs\UpdateQuestionStatsJob;
use App\Models\Attempt;
use App\Models\AttemptItem;
use App\Models\AttemptResponse;
use App\Models\QuestionStat;
use App\Models\RegradeDecision;
use App\Models\Question;
use App\Models\RubricScore;
use App\Models\User;

it('ignores unreleased attempts in class and student weakness reports', function (): void {
    $student = User::factory()->student()->create();
    $question = Question::factory()->create(['tags' => ['math']]);
    $version = $question->createVersion([
        'type' => 'mcq',
        'payload' => ['stem' => '2 + 2 = ?', 'tags' => ['math']],
        'answer_key' => ['correct_choice_id' => 'A'],
        'rubric' => null,
    ]);

    $releasedAttempt = Attempt::query()->create([
        'exam_id' => 101,
        'student_id' => $student->id,
        'submitted_at' => now()->subDay(),
        'grade_state' => 'released',
        'release_at' => now()->subDay(),
    ]);
    $unreleasedAttempt = Attempt::query()->create([
        'exam_id' => 101,
        'student_id' => $student->id,
        'submitted_at' => now(),
        'grade_state' => 'graded',
        'release_at' => now()->addDay(),
    ]);

    $releasedItem = AttemptItem::query()->create([
        'attempt_id' => $releasedAttempt->id,
        'question_version_id' => $version->id,
        'order' => 1,
        'max_points' => 10,
    ]);
    $unreleasedItem = AttemptItem::query()->create([
        'attempt_id' => $unreleasedAttempt->id,
        'question_version_id' => $version->id,
        'order' => 1,
        'max_points' => 10,
    ]);

    AttemptResponse::query()->create([
        'attempt_item_id' => $releasedItem->id,
        'response_payload' => ['choice_id' => 'A'],
        'submitted_at' => $releasedAttempt->submitted_at,
    ]);
    AttemptResponse::query()->create([
        'attempt_item_id' => $unreleasedItem->id,
        'response_payload' => ['choice_id' => 'B'],
        'submitted_at' => $unreleasedAttempt->submitted_at,
    ]);

    $classRows = app(ClassWeaknessReport::class)->execute(classId: 101, mode: 'tag');
    $studentRows = app(StudentWeaknessReport::class)->execute(studentId: $student->id, classId: 101, mode: 'tag');

    $classRow = collect($classRows)->firstWhere('key', 'math');
    $studentRow = collect($studentRows)->firstWhere('key', 'math');

    expect($classRow)->not->toBeNull()
        ->and($classRow['attempts'])->toBe(1)
        ->and($classRow['avg_percent'])->toBe(100.0)
        ->and($studentRow)->not->toBeNull()
        ->and($studentRow['attempts'])->toBe(1)
        ->and($studentRow['avg_percent'])->toBe(100.0);
});

it('excludes void_question drop_from_total from average percent denominator', function (): void {
    $student = User::factory()->student()->create();
    $teacher = User::factory()->teacher()->create();
    $question = Question::factory()->create(['tags' => ['physics']]);
    $version = $question->createVersion([
        'type' => 'essay',
        'payload' => ['stem' => 'Explain gravity.', 'tags' => ['physics']],
        'answer_key' => ['guide' => 'N/A'],
        'rubric' => ['criteria' => ['accuracy']],
    ]);

    $attempt = Attempt::query()->create([
        'exam_id' => 102,
        'student_id' => $student->id,
        'submitted_at' => now()->subHours(3),
        'grade_state' => 'released',
        'release_at' => now()->subHours(2),
    ]);

    $itemIncluded = AttemptItem::query()->create([
        'attempt_id' => $attempt->id,
        'question_version_id' => $version->id,
        'order' => 1,
        'max_points' => 10,
    ]);
    $itemDropped = AttemptItem::query()->create([
        'attempt_id' => $attempt->id,
        'question_version_id' => $version->id,
        'order' => 2,
        'max_points' => 10,
    ]);

    RubricScore::query()->create([
        'attempt_item_id' => $itemIncluded->id,
        'scores' => [['criterion' => 'accuracy', 'points' => 5]],
        'total_points' => 5,
        'graded_by' => $teacher->id,
        'graded_at' => now(),
        'is_draft' => false,
    ]);
    RubricScore::query()->create([
        'attempt_item_id' => $itemDropped->id,
        'scores' => [['criterion' => 'accuracy', 'points' => 4]],
        'total_points' => 4,
        'graded_by' => $teacher->id,
        'graded_at' => now(),
        'is_draft' => false,
    ]);

    RegradeDecision::query()->create([
        'scope' => 'attempt_item',
        'attempt_item_id' => $itemDropped->id,
        'question_version_id' => null,
        'decision_type' => 'void_question',
        'payload' => ['mode' => 'drop_from_total'],
        'decided_by' => $teacher->id,
        'decided_at' => now(),
        'created_at' => now(),
    ]);

    $rows = app(ClassWeaknessReport::class)->execute(classId: 102, mode: 'tag');
    $row = collect($rows)->firstWhere('key', 'physics');

    expect($row)->not->toBeNull()
        ->and($row['attempts'])->toBe(2)
        ->and($row['avg_percent'])->toBe(50.0);
});

it('reflects partial_credit override in average score', function (): void {
    $student = User::factory()->student()->create();
    $teacher = User::factory()->teacher()->create();
    $question = Question::factory()->create(['tags' => ['algebra']]);
    $version = $question->createVersion([
        'type' => 'essay',
        'payload' => ['stem' => 'Solve x + 2 = 5', 'tags' => ['algebra']],
        'answer_key' => ['guide' => 'x=3'],
        'rubric' => ['criteria' => ['accuracy']],
    ]);

    $attempt = Attempt::query()->create([
        'exam_id' => 103,
        'student_id' => $student->id,
        'submitted_at' => now()->subHours(3),
        'grade_state' => 'released',
        'release_at' => now()->subHours(2),
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

    RegradeDecision::query()->create([
        'scope' => 'attempt_item',
        'attempt_item_id' => $item->id,
        'question_version_id' => null,
        'decision_type' => 'partial_credit',
        'payload' => ['new_points' => 7],
        'decided_by' => $teacher->id,
        'decided_at' => now(),
        'created_at' => now(),
    ]);

    $rows = app(StudentWeaknessReport::class)->execute(studentId: $student->id, classId: 103, mode: 'tag');
    $row = collect($rows)->firstWhere('key', 'algebra');

    expect($row)->not->toBeNull()
        ->and($row['avg_score'])->toBe(7.0)
        ->and($row['avg_percent'])->toBe(70.0);
});

it('updates question stats per question_version with usage and correct rates', function (): void {
    $student = User::factory()->student()->create();

    $question = Question::factory()->create();
    $versionOne = $question->createVersion([
        'type' => 'mcq',
        'payload' => ['stem' => '2 + 2 = ?'],
        'answer_key' => ['correct_choice_id' => 'A'],
        'rubric' => null,
    ]);
    $versionTwo = $question->createVersion([
        'type' => 'mcq',
        'payload' => ['stem' => '3 + 3 = ?'],
        'answer_key' => ['correct_choice_id' => 'B'],
        'rubric' => null,
    ]);

    $attemptOne = Attempt::query()->create([
        'exam_id' => 104,
        'student_id' => $student->id,
        'submitted_at' => now()->subDay(),
        'grade_state' => 'released',
        'release_at' => now()->subDay(),
    ]);
    $attemptTwo = Attempt::query()->create([
        'exam_id' => 104,
        'student_id' => $student->id,
        'submitted_at' => now(),
        'grade_state' => 'released',
        'release_at' => now(),
    ]);

    $itemOne = AttemptItem::query()->create([
        'attempt_id' => $attemptOne->id,
        'question_version_id' => $versionOne->id,
        'order' => 1,
        'max_points' => 5,
    ]);
    $itemTwo = AttemptItem::query()->create([
        'attempt_id' => $attemptTwo->id,
        'question_version_id' => $versionOne->id,
        'order' => 1,
        'max_points' => 5,
    ]);
    $itemThree = AttemptItem::query()->create([
        'attempt_id' => $attemptTwo->id,
        'question_version_id' => $versionTwo->id,
        'order' => 1,
        'max_points' => 5,
    ]);

    AttemptResponse::query()->create([
        'attempt_item_id' => $itemOne->id,
        'response_payload' => ['choice_id' => 'A'],
        'submitted_at' => $attemptOne->submitted_at,
    ]);
    AttemptResponse::query()->create([
        'attempt_item_id' => $itemTwo->id,
        'response_payload' => ['choice_id' => 'B'],
        'submitted_at' => $attemptTwo->submitted_at,
    ]);
    AttemptResponse::query()->create([
        'attempt_item_id' => $itemThree->id,
        'response_payload' => ['choice_id' => 'B'],
        'submitted_at' => $attemptTwo->submitted_at,
    ]);

    (new UpdateQuestionStatsJob(classId: 104))->handle();

    $versionOneStat = QuestionStat::query()
        ->where('question_version_id', $versionOne->id)
        ->first();
    $versionTwoStat = QuestionStat::query()
        ->where('question_version_id', $versionTwo->id)
        ->first();

    expect($versionOneStat)->not->toBeNull()
        ->and($versionOneStat?->usage_count)->toBe(2)
        ->and($versionOneStat?->correct_count)->toBe(1)
        ->and($versionOneStat?->incorrect_count)->toBe(1)
        ->and((float) $versionOneStat?->correct_rate)->toBe(50.0);

    expect($versionTwoStat)->not->toBeNull()
        ->and($versionTwoStat?->usage_count)->toBe(1)
        ->and($versionTwoStat?->correct_count)->toBe(1)
        ->and($versionTwoStat?->incorrect_count)->toBe(0)
        ->and((float) $versionTwoStat?->correct_rate)->toBe(100.0);
});

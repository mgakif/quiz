<?php

declare(strict_types=1);

use App\Jobs\ComputeTermGradesJob;
use App\Models\Assessment;
use App\Models\Attempt;
use App\Models\AttemptItem;
use App\Models\AttemptResponse;
use App\Models\Question;
use App\Models\Term;
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

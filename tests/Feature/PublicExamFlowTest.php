<?php

declare(strict_types=1);

use App\Domain\Gradebook\ComputeStudentTermGrade;
use App\Domain\Leaderboards\Services\LeaderboardService;
use App\Models\Assessment;
use App\Models\Attempt;
use App\Models\AttemptItem;
use App\Models\AttemptResponse;
use App\Models\Exam;
use App\Models\PublicExamLink;
use App\Models\Question;
use App\Models\RubricScore;
use App\Models\StudentProfile;
use App\Models\Term;
use App\Models\User;

it('shows exam by valid public token and start creates guest attempt', function (): void {
    $teacher = User::factory()->teacher()->create();
    Term::query()->create([
        'name' => '2025-2026 Spring',
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
        'is_active' => true,
    ]);

    Exam::query()->create([
        'id' => 7001,
        'title' => 'Public Midterm',
        'class_id' => 7,
    ]);

    $link = PublicExamLink::query()->create([
        'exam_id' => 7001,
        'token' => str_repeat('a', 64),
        'is_enabled' => true,
        'require_name' => true,
        'created_by' => $teacher->id,
    ]);

    $this->getJson('/public/'.$link->token)
        ->assertSuccessful()
        ->assertJsonPath('exam_id', 7001)
        ->assertJsonPath('title', 'Public Midterm');

    $this->postJson('/public/'.$link->token.'/start', [
        'display_name' => 'Guest One',
    ])
        ->assertCreated()
        ->assertJsonPath('exam_id', 7001);

    $attempt = Attempt::query()->latest('id')->first();

    expect($attempt)->not->toBeNull()
        ->and($attempt?->student_id)->toBeNull()
        ->and($attempt?->guest_id)->not->toBeNull()
        ->and($attempt?->public_exam_link_id)->toBe($link->id);
});

it('denies invalid or expired public token', function (): void {
    $teacher = User::factory()->teacher()->create();
    Term::query()->create([
        'name' => '2025-2026 Spring',
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
        'is_active' => true,
    ]);

    Exam::query()->create([
        'id' => 7002,
        'title' => 'Expired Public Quiz',
        'class_id' => 7,
    ]);

    PublicExamLink::query()->create([
        'exam_id' => 7002,
        'token' => str_repeat('b', 64),
        'is_enabled' => true,
        'expires_at' => now()->subMinute(),
        'created_by' => $teacher->id,
    ]);

    $this->getJson('/public/'.str_repeat('x', 64))->assertNotFound();
    $this->getJson('/public/'.str_repeat('b', 64))->assertNotFound();
    $this->postJson('/public/'.str_repeat('b', 64).'/start', [
        'display_name' => 'Late Guest',
    ])->assertNotFound();
});

it('public result endpoint respects release gate', function (): void {
    $teacher = User::factory()->teacher()->create();
    Term::query()->create([
        'name' => '2025-2026 Spring',
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
        'is_active' => true,
    ]);

    Exam::query()->create([
        'id' => 7003,
        'title' => 'Release Locked Public Quiz',
        'class_id' => 7,
    ]);

    $link = PublicExamLink::query()->create([
        'exam_id' => 7003,
        'token' => str_repeat('c', 64),
        'is_enabled' => true,
        'require_name' => false,
        'created_by' => $teacher->id,
    ]);

    $startResponse = $this->postJson('/public/'.$link->token.'/start', []);
    $startResponse->assertCreated();

    $attemptId = (int) $startResponse->json('attempt_id');
    $attempt = Attempt::query()->findOrFail($attemptId);

    $question = Question::factory()->create([
        'created_by' => $teacher->id,
    ]);
    $version = $question->createVersion([
        'type' => 'essay',
        'payload' => ['text' => 'Explain gravity'],
        'answer_key' => ['guide' => 'N/A'],
        'rubric' => ['criteria' => ['accuracy']],
    ]);

    $item = AttemptItem::query()->create([
        'attempt_id' => $attempt->id,
        'question_version_id' => $version->id,
        'order' => 1,
        'max_points' => 10,
    ]);

    RubricScore::query()->create([
        'attempt_item_id' => $item->id,
        'scores' => [['criterion' => 'accuracy', 'points' => 8]],
        'total_points' => 8,
        'graded_by' => $teacher->id,
        'graded_at' => now(),
        'is_draft' => false,
    ]);

    $attempt->update([
        'submitted_at' => now()->subMinute(),
        'grade_state' => 'graded',
        'release_at' => now()->addHour(),
    ]);

    $this->getJson('/public/attempts/'.$attempt->id.'/result')
        ->assertSuccessful()
        ->assertJsonMissingPath('total_points');

    $attempt->update([
        'grade_state' => 'released',
        'release_at' => now()->subMinute(),
    ]);

    $this->getJson('/public/attempts/'.$attempt->id.'/result')
        ->assertSuccessful()
        ->assertJsonPath('total_points', 8);
});

it('excludes guest attempts from gradebook and leaderboard computations', function (): void {
    $teacher = User::factory()->teacher()->create();
    $student = User::factory()->student()->create();

    $term = Term::query()->create([
        'name' => '2025-2026 Spring',
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
        'is_active' => true,
    ]);

    Exam::query()->create([
        'id' => 7004,
        'title' => 'Mixed Exam',
        'class_id' => 7,
    ]);

    $assessment = Assessment::query()->where('legacy_exam_id', 7004)->first();
    expect($assessment)->not->toBeNull();

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

    $studentAttempt = Attempt::query()->create([
        'exam_id' => 7004,
        'student_id' => $student->id,
        'guest_id' => null,
        'started_at' => now()->subHours(2),
        'submitted_at' => now()->subHour(),
        'grade_state' => 'released',
        'release_at' => now()->subMinute(),
    ]);

    $studentItem = AttemptItem::query()->create([
        'attempt_id' => $studentAttempt->id,
        'question_version_id' => $version->id,
        'order' => 1,
        'max_points' => 10,
    ]);
    AttemptResponse::query()->create([
        'attempt_item_id' => $studentItem->id,
        'response_payload' => ['choice_id' => 'A'],
        'submitted_at' => now()->subHour(),
    ]);

    $guestAttempt = Attempt::query()->create([
        'exam_id' => 7004,
        'student_id' => null,
        'guest_id' => (string) \App\Models\GuestUser::query()->create(['display_name' => 'Guest'])->id,
        'started_at' => now()->subHours(2),
        'submitted_at' => now()->subHour(),
        'grade_state' => 'released',
        'release_at' => now()->subMinute(),
    ]);

    $guestItem = AttemptItem::query()->create([
        'attempt_id' => $guestAttempt->id,
        'question_version_id' => $version->id,
        'order' => 1,
        'max_points' => 10,
    ]);
    AttemptResponse::query()->create([
        'attempt_item_id' => $guestItem->id,
        'response_payload' => ['choice_id' => 'B'],
        'submitted_at' => now()->subHour(),
    ]);

    StudentProfile::query()->create([
        'student_id' => $student->id,
        'class_id' => 7004,
        'nickname' => 'OnlyStudent',
        'show_on_leaderboard' => true,
    ]);

    $result = app(ComputeStudentTermGrade::class)->execute($term, $student, 7, false);

    expect($result['computed_grade'])->toBe(100.0)
        ->and($result['missing_assessments_count'])->toBe(0);

    $payload = app(LeaderboardService::class)->computeAndStore(7004, 'all_time');

    expect($payload['entries'])->toHaveCount(1)
        ->and($payload['entries'][0]['student_id'])->toBe($student->id);
});

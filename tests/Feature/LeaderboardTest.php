<?php

declare(strict_types=1);

use App\Domain\Leaderboards\Services\LeaderboardService;
use App\Domain\Regrade\Actions\ApplyDecisionAndRegrade;
use App\Jobs\ComputeLeaderboardJob;
use App\Jobs\ReleaseDueGradesJob;
use App\Models\Attempt;
use App\Models\AttemptItem;
use App\Models\AttemptResponse;
use App\Models\Leaderboard;
use App\Models\Question;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Cache::flush();
});

it('excludes unreleased attempts from leaderboard computation', function (): void {
    $student = User::factory()->student()->create();
    $question = Question::factory()->create();
    $version = $question->createVersion([
        'type' => 'mcq',
        'payload' => ['stem' => '2 + 2 = ?'],
        'answer_key' => ['correct_choice_id' => 'A'],
        'rubric' => null,
    ]);

    $releasedAttempt = Attempt::query()->create([
        'exam_id' => 50,
        'student_id' => $student->id,
        'submitted_at' => now()->subDay(),
        'grade_state' => 'released',
        'release_at' => now()->subDay(),
    ]);
    $unreleasedAttempt = Attempt::query()->create([
        'exam_id' => 50,
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
        'submitted_at' => now()->subDay(),
    ]);
    AttemptResponse::query()->create([
        'attempt_item_id' => $unreleasedItem->id,
        'response_payload' => ['choice_id' => 'A'],
        'submitted_at' => now(),
    ]);

    StudentProfile::query()->create([
        'student_id' => $student->id,
        'class_id' => 50,
        'nickname' => 'Falcon',
        'show_on_leaderboard' => true,
    ]);

    $leaderboard = app(LeaderboardService::class)->computeAndStore(50, 'all_time');

    expect($leaderboard['entries'])->toHaveCount(1)
        ->and($leaderboard['entries'][0]['nickname'])->toBe('Falcon')
        ->and($leaderboard['entries'][0]['percent'])->toBe(100.0)
        ->and($leaderboard['entries'][0]['attempts_count'])->toBe(1);
});

it('excludes opted-out students from leaderboard', function (): void {
    $studentVisible = User::factory()->student()->create();
    $studentHidden = User::factory()->student()->create();
    $question = Question::factory()->create();
    $version = $question->createVersion([
        'type' => 'mcq',
        'payload' => ['stem' => 'Capital of France?'],
        'answer_key' => ['correct_choice_id' => 'A'],
        'rubric' => null,
    ]);

    foreach ([$studentVisible, $studentHidden] as $student) {
        $attempt = Attempt::query()->create([
            'exam_id' => 90,
            'student_id' => $student->id,
            'submitted_at' => now()->subDay(),
            'grade_state' => 'released',
            'release_at' => now()->subDay(),
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
            'submitted_at' => now()->subDay(),
        ]);
    }

    StudentProfile::query()->create([
        'student_id' => $studentVisible->id,
        'class_id' => 90,
        'nickname' => 'Nova',
        'show_on_leaderboard' => true,
    ]);
    StudentProfile::query()->create([
        'student_id' => $studentHidden->id,
        'class_id' => 90,
        'nickname' => 'Ghost',
        'show_on_leaderboard' => false,
    ]);

    $leaderboard = app(LeaderboardService::class)->computeAndStore(90, 'all_time');

    expect($leaderboard['entries'])->toHaveCount(1)
        ->and($leaderboard['entries'][0]['nickname'])->toBe('Nova');
});

it('enforces nickname uniqueness per class', function (): void {
    $studentOne = User::factory()->student()->create();
    $studentTwo = User::factory()->student()->create();

    StudentProfile::query()->create([
        'student_id' => $studentOne->id,
        'class_id' => 42,
        'nickname' => 'Echo',
        'show_on_leaderboard' => true,
    ]);

    expect(function () use ($studentTwo): void {
        StudentProfile::query()->create([
            'student_id' => $studentTwo->id,
            'class_id' => 42,
            'nickname' => 'Echo',
            'show_on_leaderboard' => true,
        ]);
    })->toThrow(QueryException::class);
});

it('saves leaderboard snapshot and populates cache', function (): void {
    $student = User::factory()->student()->create();
    $question = Question::factory()->create();
    $version = $question->createVersion([
        'type' => 'mcq',
        'payload' => ['stem' => '1 + 1 = ?'],
        'answer_key' => ['correct_choice_id' => 'B'],
        'rubric' => null,
    ]);

    $attempt = Attempt::query()->create([
        'exam_id' => 42,
        'student_id' => $student->id,
        'submitted_at' => now()->subDay(),
        'grade_state' => 'released',
        'release_at' => now()->subDay(),
    ]);
    $item = AttemptItem::query()->create([
        'attempt_id' => $attempt->id,
        'question_version_id' => $version->id,
        'order' => 1,
        'max_points' => 10,
    ]);
    AttemptResponse::query()->create([
        'attempt_item_id' => $item->id,
        'response_payload' => ['choice_id' => 'B'],
        'submitted_at' => now()->subDay(),
    ]);

    StudentProfile::query()->create([
        'student_id' => $student->id,
        'class_id' => 42,
        'nickname' => 'Echo',
        'show_on_leaderboard' => true,
    ]);

    $payload = app(LeaderboardService::class)->computeAndStore(42, 'all_time');

    expect($payload['entries'])->toHaveCount(1);

    $snapshot = Leaderboard::query()
        ->where('class_id', 42)
        ->where('period', 'all_time')
        ->whereNull('start_date')
        ->whereNull('end_date')
        ->first();

    expect($snapshot)->not->toBeNull()
        ->and(is_array($snapshot?->payload))->toBeTrue();

    expect(Cache::has('leaderboard:42:all_time:null:null'))->toBeTrue();
});

it('dispatches leaderboard recompute when regrade decision is applied', function (): void {
    Queue::fake();

    $teacher = User::factory()->teacher()->create();
    $student = User::factory()->student()->create();
    $question = Question::factory()->create();
    $version = $question->createVersion([
        'type' => 'essay',
        'payload' => ['stem' => 'Explain gravity'],
        'answer_key' => ['guide' => 'N/A'],
        'rubric' => ['criteria' => ['accuracy']],
    ]);

    $attempt = Attempt::query()->create([
        'exam_id' => 77,
        'student_id' => $student->id,
        'submitted_at' => now()->subDay(),
        'grade_state' => 'released',
        'release_at' => now()->subDay(),
    ]);

    $item = AttemptItem::query()->create([
        'attempt_id' => $attempt->id,
        'question_version_id' => $version->id,
        'order' => 1,
        'max_points' => 10,
    ]);

    app(ApplyDecisionAndRegrade::class)->execute(
        teacher: $teacher,
        scope: 'attempt_item',
        decisionType: 'partial_credit',
        payload: [
            'new_points' => 7,
            'reason' => 'Accepted alternative valid reasoning',
        ],
        attemptItem: $item,
    );

    Queue::assertPushed(ComputeLeaderboardJob::class, fn (ComputeLeaderboardJob $job): bool => $job->classId === 77 && $job->period === 'all_time');
    Queue::assertPushed(ComputeLeaderboardJob::class, fn (ComputeLeaderboardJob $job): bool => $job->classId === 77 && $job->period === 'weekly');
});

it('dispatches leaderboard recompute when attempts are released', function (): void {
    Queue::fake();

    $student = User::factory()->student()->create();

    Attempt::query()->create([
        'exam_id' => 66,
        'student_id' => $student->id,
        'submitted_at' => now()->subHour(),
        'grade_state' => 'graded',
        'release_at' => now()->subMinute(),
    ]);

    (new ReleaseDueGradesJob())->handle();

    Queue::assertPushed(ComputeLeaderboardJob::class, fn (ComputeLeaderboardJob $job): bool => $job->classId === 66 && $job->period === 'all_time');
    Queue::assertPushed(ComputeLeaderboardJob::class, fn (ComputeLeaderboardJob $job): bool => $job->classId === 66 && $job->period === 'weekly');
});

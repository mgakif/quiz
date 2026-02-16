<?php

declare(strict_types=1);

use App\Models\AiGrading;
use App\Models\Appeal;
use App\Models\Attempt;
use App\Models\AttemptItem;
use App\Models\Leaderboard;
use App\Models\QuestionStat;
use App\Models\RegradeDecision;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;

it('seeds deterministic demo data with regrade and leaderboard artifacts', function (): void {
    $this->artisan('demo:seed')->assertSuccessful();

    $demoClassId = 901;
    $quizSlugs = ['9a_quiz_1', '9a_quiz_2', '9a_quiz_3', '9a_quiz_4'];

    $teacher = User::query()->where('email', 'demo.teacher@quiz.local')->first();

    expect($teacher)->not->toBeNull();

    $studentIds = User::query()
        ->where('role', User::ROLE_STUDENT)
        ->where('email', 'like', 'demo.student%@quiz.local')
        ->pluck('id');

    expect($studentIds)->toHaveCount(12)
        ->and(
            StudentProfile::query()
                ->whereIn('student_id', $studentIds)
                ->where('class_id', $demoClassId)
                ->count()
        )->toBe(12);

    $attemptCounts = Attempt::query()
        ->whereIn('student_id', $studentIds)
        ->where('exam_id', $demoClassId)
        ->selectRaw('student_id, COUNT(*) as aggregate_count')
        ->groupBy('student_id')
        ->pluck('aggregate_count', 'student_id');

    expect($attemptCounts->count())->toBe(12)
        ->and((int) $attemptCounts->min())->toBeGreaterThanOrEqual(2)
        ->and(
            Attempt::query()
                ->where('exam_id', $demoClassId)
                ->where('release_at', '>', now())
                ->count()
        )->toBeGreaterThan(0);

    foreach ($quizSlugs as $quizSlug) {
        $distribution = DB::table('questions')
            ->join('question_versions', 'question_versions.question_id', '=', 'questions.id')
            ->whereJsonContains('questions.tags', $quizSlug)
            ->selectRaw('question_versions.type, COUNT(*) as aggregate_count')
            ->groupBy('question_versions.type')
            ->pluck('aggregate_count', 'question_versions.type');

        expect((int) ($distribution['mcq'] ?? 0))->toBe(6)
            ->and((int) ($distribution['matching'] ?? 0))->toBe(2)
            ->and((int) ($distribution['short'] ?? 0))->toBe(1)
            ->and((int) ($distribution['essay'] ?? 0))->toBe(1);
    }

    expect(
        AiGrading::query()
            ->whereJsonContains('flags', 'needs_teacher_review')
            ->count()
    )->toBeGreaterThan(0)
        ->and(QuestionStat::query()->whereNotNull('question_version_id')->count())->toBeGreaterThan(0);

    expect(Appeal::query()->count())->toBe(2)
        ->and(Appeal::query()->where('status', Appeal::STATUS_RESOLVED)->count())->toBe(2)
        ->and(RegradeDecision::query()->where('decision_type', 'partial_credit')->count())->toBeGreaterThan(0)
        ->and(
            RegradeDecision::query()
                ->where('decision_type', 'void_question')
                ->where('payload->mode', 'drop_from_total')
                ->count()
        )->toBeGreaterThan(0);

    $voidDecision = RegradeDecision::query()
        ->where('decision_type', 'void_question')
        ->orderByDesc('id')
        ->first();

    expect($voidDecision)->not->toBeNull();

    $voidedItem = AttemptItem::query()->find($voidDecision?->attempt_item_id);

    expect((float) ($voidedItem?->max_points ?? 1))->toBe(0.0);

    expect(
        Leaderboard::query()
            ->where('class_id', $demoClassId)
            ->whereIn('period', ['weekly', 'all_time'])
            ->count()
    )->toBe(2);
});

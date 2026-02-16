<?php

declare(strict_types=1);

namespace App\Domain\Gradebook;

use App\Domain\Analytics\ScoreExtractor;
use App\Domain\Attempts\Policies\GradeReleasePolicy;
use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Models\Assessment;
use App\Models\Attempt;
use App\Models\AttemptItem;
use App\Models\StudentTermGrade;
use App\Models\Term;
use App\Models\User;
use Illuminate\Support\Collection;

class ComputeStudentTermGrade
{
    public function __construct(
        private ScoreExtractor $scoreExtractor,
        private GradeReleasePolicy $gradeReleasePolicy,
        private RecordAuditEvent $recordAuditEvent,
    ) {
    }

    /**
     * @return array{
     *     term_id:string,
     *     student_id:int,
     *     computed_grade:float|null,
     *     missing_assessments_count:int,
     *     assessments:array<int, array{
     *         assessment_id:string,
     *         title:string,
     *         category:string,
     *         weight:float,
     *         attempt_status:string,
     *         percent:float|null,
     *         earned_points:float|null,
     *         max_points:float|null,
     *         released_at:string|null,
     *         contribution:float,
     *         message:string|null
     *     }>
     * }
     */
    public function execute(Term $term, User $student, ?int $classId = null, bool $persist = true): array
    {
        $assessments = Assessment::query()
            ->where('term_id', $term->id)
            ->where('published', true)
            ->when($classId !== null, fn ($query) => $query->where('class_id', $classId))
            ->orderBy('scheduled_at')
            ->orderBy('title')
            ->get();

        $attemptsByExamId = $this->loadAttemptsByExamId($student, $assessments);
        $strategy = (string) config('gradebook.attempt_strategy', 'latest_released');
        $missingCount = 0;
        $weightSum = 0.0;
        $contributionSum = 0.0;

        $rows = $assessments
            ->map(function (Assessment $assessment) use (
                $attemptsByExamId,
                $strategy,
                &$missingCount,
                &$weightSum,
                &$contributionSum,
            ): array {
                $weight = round((float) $assessment->weight, 2);
                $weightSum += $weight;

                /** @var Collection<int, Attempt> $attempts */
                $attempts = $attemptsByExamId->get((int) $assessment->legacy_exam_id, collect());
                $releasedAttempts = $attempts
                    ->filter(fn (Attempt $attempt): bool => $this->gradeReleasePolicy->canStudentSeeGrades($attempt))
                    ->values();

                $selectedReleasedAttempt = $this->selectAttemptByStrategy($releasedAttempts, $strategy);

                if (! $selectedReleasedAttempt instanceof Attempt) {
                    $missingCount++;
                    $latestUnreleased = $attempts
                        ->sortByDesc(fn (Attempt $attempt): int => (int) ($attempt->submitted_at?->timestamp ?? 0))
                        ->first();

                    $status = $latestUnreleased instanceof Attempt ? 'unreleased' : 'missing';

                    return [
                        'assessment_id' => $assessment->id,
                        'title' => $assessment->title,
                        'category' => $assessment->category,
                        'weight' => $weight,
                        'attempt_status' => $status,
                        'percent' => null,
                        'earned_points' => null,
                        'max_points' => null,
                        'released_at' => null,
                        'contribution' => 0.0,
                        'message' => $status === 'unreleased' ? 'Notlar su tarihte aciklanacak.' : 'Attempt missing.',
                    ];
                }

                [$earnedPoints, $maxPoints] = $this->scoreAttempt($selectedReleasedAttempt);
                $percent = $maxPoints > 0 ? round(($earnedPoints / $maxPoints) * 100, 2) : 0.0;
                $contribution = round(($percent / 100) * $weight, 4);
                $contributionSum += $contribution;

                return [
                    'assessment_id' => $assessment->id,
                    'title' => $assessment->title,
                    'category' => $assessment->category,
                    'weight' => $weight,
                    'attempt_status' => 'released',
                    'percent' => $percent,
                    'earned_points' => $earnedPoints,
                    'max_points' => $maxPoints,
                    'released_at' => $selectedReleasedAttempt->release_at?->toIso8601String(),
                    'contribution' => $contribution,
                    'message' => null,
                ];
            })
            ->values()
            ->all();

        $computedGrade = $weightSum > 0
            ? round(($contributionSum / $weightSum) * 100, 2)
            : null;

        if ($persist) {
            StudentTermGrade::query()->updateOrCreate(
                [
                    'term_id' => $term->id,
                    'student_id' => $student->id,
                ],
                [
                    'computed_grade' => $computedGrade,
                    'computed_at' => now(),
                ],
            );

            $this->recordAuditEvent->execute(
                actor: null,
                actorType: 'system',
                eventType: 'term_grade_computed',
                entityType: 'student_term_grade',
                entityId: sprintf('%s:%d', $term->id, $student->id),
                meta: [
                    'term_id' => $term->id,
                    'student_id' => $student->id,
                    'grade' => $computedGrade,
                    'missing_assessments_count' => $missingCount,
                ],
            );
        }

        return [
            'term_id' => $term->id,
            'student_id' => $student->id,
            'computed_grade' => $computedGrade,
            'missing_assessments_count' => $missingCount,
            'assessments' => $rows,
        ];
    }

    /**
     * @param  Collection<int, Attempt>  $attempts
     */
    private function selectAttemptByStrategy(Collection $attempts, string $strategy): ?Attempt
    {
        if ($attempts->isEmpty()) {
            return null;
        }

        if ($strategy === 'highest_score') {
            $ranked = $attempts
                ->map(function (Attempt $attempt): array {
                    [$earned, $max] = $this->scoreAttempt($attempt);
                    $percent = $max > 0 ? round(($earned / $max) * 100, 4) : 0.0;

                    return [
                        'attempt' => $attempt,
                        'percent' => $percent,
                        'submitted_at' => (int) ($attempt->submitted_at?->timestamp ?? 0),
                    ];
                })
                ->sortByDesc('submitted_at')
                ->sortByDesc('percent')
                ->values();

            $selected = $ranked->first();

            return is_array($selected) ? ($selected['attempt'] ?? null) : null;
        }

        return $attempts
            ->sortByDesc(fn (Attempt $attempt): int => (int) ($attempt->submitted_at?->timestamp ?? 0))
            ->sortByDesc('id')
            ->first();
    }

    /**
     * @return array{0:float,1:float}
     */
    private function scoreAttempt(Attempt $attempt): array
    {
        $attempt->loadMissing(['items.questionVersion', 'items.response', 'items.rubricScore']);

        $totals = $attempt->items
            ->map(function (AttemptItem $item): array {
                $score = $this->scoreExtractor->extractForAttemptItem($item);

                return [
                    'earned' => round((float) ($score['earned_points'] ?? 0), 2),
                    'max' => round((float) ($score['max_points'] ?? 0), 2),
                ];
            })
            ->values();

        return [
            round((float) $totals->sum('earned'), 2),
            round((float) $totals->sum('max'), 2),
        ];
    }

    /**
     * @param  Collection<int, Assessment>  $assessments
     * @return Collection<int, Collection<int, Attempt>>
     */
    private function loadAttemptsByExamId(User $student, Collection $assessments): Collection
    {
        $examIds = $assessments
            ->pluck('legacy_exam_id')
            ->map(fn (mixed $value): int => (int) $value)
            ->filter(fn (int $value): bool => $value > 0)
            ->values()
            ->all();

        if ($examIds === []) {
            return collect();
        }

        return Attempt::query()
            ->with(['items.questionVersion', 'items.response', 'items.rubricScore'])
            ->where('student_id', $student->id)
            ->whereIn('exam_id', $examIds)
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->get()
            ->groupBy('exam_id');
    }
}

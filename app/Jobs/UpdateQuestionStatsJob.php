<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Analytics\ScoreExtractor;
use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Models\QuestionStat;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class UpdateQuestionStatsJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public ?int $classId = null,
        public ?string $startDate = null,
        public ?string $endDate = null,
    ) {
    }

    public function handle(): void
    {
        $scoreExtractor = app(ScoreExtractor::class);
        $recordAuditEvent = app(RecordAuditEvent::class);

        $startDate = $this->startDate !== null
            ? CarbonImmutable::parse($this->startDate)->startOfDay()
            : now()->subDays(90)->startOfDay()->toImmutable();
        $endDate = $this->endDate !== null
            ? CarbonImmutable::parse($this->endDate)->endOfDay()
            : now()->endOfDay()->toImmutable();

        $scoredItemsQuery = $scoreExtractor->releasedScoredItemsQuery(
            startDate: $startDate,
            endDate: $endDate,
            classId: $this->classId,
        );

        $aggregatedRows = DB::query()
            ->fromSub($scoredItemsQuery, 'scored_items')
            ->selectRaw('question_id')
            ->selectRaw('question_version_id')
            ->selectRaw('COUNT(*) as usage_count')
            ->selectRaw("SUM(CASE WHEN is_auto_gradable = 1 AND is_auto_correct = 1 THEN 1 ELSE 0 END) as correct_count")
            ->selectRaw("SUM(CASE WHEN is_auto_gradable = 1 AND is_auto_correct = 0 THEN 1 ELSE 0 END) as incorrect_count")
            ->selectRaw("
                CASE
                    WHEN SUM(CASE WHEN is_auto_gradable = 1 THEN 1 ELSE 0 END) > 0
                        THEN ROUND(
                            (
                                SUM(CASE WHEN is_auto_gradable = 1 AND is_auto_correct = 1 THEN 1 ELSE 0 END) * 100.0
                            ) / SUM(CASE WHEN is_auto_gradable = 1 THEN 1 ELSE 0 END),
                            2
                        )
                    ELSE NULL
                END as correct_rate
            ")
            ->selectRaw('ROUND(AVG(earned_points), 2) as avg_score')
            ->selectRaw('MAX(used_at) as last_used_at')
            ->groupBy('question_id', 'question_version_id')
            ->get();

        $upsertPayload = $aggregatedRows
            ->map(fn (object $row): array => [
                'question_id' => (int) $row->question_id,
                'question_version_id' => (int) $row->question_version_id,
                'usage_count' => (int) $row->usage_count,
                'correct_count' => (int) $row->correct_count,
                'incorrect_count' => (int) $row->incorrect_count,
                'correct_rate' => $row->correct_rate !== null ? (float) $row->correct_rate : null,
                'avg_score' => $row->avg_score !== null ? (float) $row->avg_score : null,
                'last_used_at' => $row->last_used_at,
                'updated_at' => now(),
                'created_at' => now(),
            ])
            ->all();

        if ($upsertPayload !== []) {
            QuestionStat::query()->upsert(
                $upsertPayload,
                ['question_version_id'],
                ['question_id', 'usage_count', 'correct_count', 'incorrect_count', 'correct_rate', 'avg_score', 'last_used_at', 'updated_at'],
            );
        }

        $recordAuditEvent->execute(
            actor: null,
            actorType: 'system',
            eventType: 'question_stats_updated',
            entityType: 'question_stats',
            entityId: 'batch',
            meta: [
                'count' => count($upsertPayload),
                'class_id' => $this->classId,
                'range' => [
                    'start' => $startDate->toIso8601String(),
                    'end' => $endDate->toIso8601String(),
                ],
            ],
        );
    }
}

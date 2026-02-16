<?php

declare(strict_types=1);

namespace App\Domain\Leaderboard;

use App\Domain\Analytics\ScoreExtractor;
use App\Models\Leaderboard;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use stdClass;

class ComputeLeaderboard
{
    private const CACHE_TTL_SECONDS = 3600;

    public function __construct(private ScoreExtractor $scoreExtractor)
    {
    }

    /**
     * @return array{
     *     class_id:int|null,
     *     period:string,
     *     start_date:string|null,
     *     end_date:string|null,
     *     computed_at:string,
     *     entries:array<int,array{
     *         rank:int,
     *         student_id:int,
     *         nickname:string,
     *         points_total:float,
     *         max_total:float,
     *         percent:float,
     *         attempts_count:int,
     *         last_attempt_at:string|null
     *     }>
     * }
     */
    public function getLeaderboard(?int $classId, string $period): array
    {
        [$startDate, $endDate] = $this->resolveDateRange($period);

        return Cache::remember(
            $this->cacheKey($classId, $period, $startDate, $endDate),
            now()->addSeconds(self::CACHE_TTL_SECONDS),
            fn (): array => $this->snapshotOrCompute($classId, $period, $startDate, $endDate),
        );
    }

    /**
     * @return array{
     *     class_id:int|null,
     *     period:string,
     *     start_date:string|null,
     *     end_date:string|null,
     *     computed_at:string,
     *     entries:array<int,array{
     *         rank:int,
     *         student_id:int,
     *         nickname:string,
     *         points_total:float,
     *         max_total:float,
     *         percent:float,
     *         attempts_count:int,
     *         last_attempt_at:string|null
     *     }>
     * }
     */
    public function computeAndStore(?int $classId, string $period): array
    {
        [$startDate, $endDate] = $this->resolveDateRange($period);

        $entries = $this->computeEntries($classId, $startDate, $endDate);
        $payload = [
            'class_id' => $classId,
            'period' => $period,
            'start_date' => $startDate?->toDateString(),
            'end_date' => $endDate?->toDateString(),
            'computed_at' => now()->toIso8601String(),
            'entries' => $entries,
        ];

        $snapshot = Leaderboard::query()->firstOrNew([
            'class_id' => $classId,
            'period' => $period,
            'start_date' => $startDate?->toDateString(),
            'end_date' => $endDate?->toDateString(),
        ]);

        if (! $snapshot->exists) {
            $snapshot->id = (string) Str::uuid();
            $snapshot->created_at = now();
        }

        $snapshot->computed_at = now();
        $snapshot->payload = $payload;
        $snapshot->save();

        Cache::put(
            $this->cacheKey($classId, $period, $startDate, $endDate),
            $payload,
            now()->addSeconds(self::CACHE_TTL_SECONDS),
        );

        return $payload;
    }

    public function cacheKey(
        ?int $classId,
        string $period,
        ?CarbonImmutable $startDate,
        ?CarbonImmutable $endDate,
    ): string {
        $scope = $classId === null ? 'global' : (string) $classId;
        $start = $startDate?->toDateString() ?? 'null';
        $end = $endDate?->toDateString() ?? 'null';

        return "leaderboard:{$scope}:{$period}:{$start}:{$end}";
    }

    /**
     * @return array{0:?CarbonImmutable,1:?CarbonImmutable}
     */
    private function resolveDateRange(string $period): array
    {
        if (! in_array($period, ['weekly', 'monthly', 'all_time'], true)) {
            throw new InvalidArgumentException('Invalid period for leaderboard.');
        }

        if ($period === 'weekly') {
            return [now()->startOfWeek()->toImmutable(), now()->endOfWeek()->toImmutable()];
        }

        if ($period === 'monthly') {
            return [now()->startOfMonth()->toImmutable(), now()->endOfMonth()->toImmutable()];
        }

        return [null, null];
    }

    /**
     * @return array<int, array{
     *     rank:int,
     *     student_id:int,
     *     nickname:string,
     *     points_total:float,
     *     max_total:float,
     *     percent:float,
     *     attempts_count:int,
     *     last_attempt_at:string|null
     * }>
     */
    private function computeEntries(
        ?int $classId,
        ?CarbonImmutable $startDate,
        ?CarbonImmutable $endDate,
    ): array {
        $scoredItems = $this->scoreExtractor->releasedScoredItemsQuery(
            startDate: $startDate,
            endDate: $endDate,
            classId: $classId,
        );

        $aggregated = DB::query()
            ->fromSub($scoredItems, 'scored')
            ->join('student_profiles as sp', function ($join): void {
                $join
                    ->on('sp.student_id', '=', 'scored.student_id')
                    ->on('sp.class_id', '=', 'scored.class_id');
            })
            ->where('sp.show_on_leaderboard', true)
            ->whereNotNull('sp.nickname')
            ->where('sp.nickname', '!=', '')
            ->selectRaw('scored.student_id')
            ->selectRaw('sp.nickname')
            ->selectRaw('ROUND(SUM(scored.earned_points), 2) AS points_total')
            ->selectRaw('ROUND(SUM(scored.effective_max_points), 2) AS max_total')
            ->selectRaw('COUNT(DISTINCT scored.attempt_item_id) AS attempts_count')
            ->selectRaw('MAX(scored.used_at) AS last_attempt_at')
            ->groupBy('scored.student_id', 'sp.nickname')
            ->orderByRaw('CASE WHEN SUM(scored.effective_max_points) > 0 THEN SUM(scored.earned_points) / SUM(scored.effective_max_points) ELSE 0 END DESC')
            ->orderByDesc('attempts_count')
            ->orderByDesc('last_attempt_at')
            ->limit(50)
            ->get();

        return $this->mapRankedEntries($aggregated);
    }

    /**
     * @param  Collection<int, stdClass>  $rows
     * @return array<int, array{
     *     rank:int,
     *     student_id:int,
     *     nickname:string,
     *     points_total:float,
     *     max_total:float,
     *     percent:float,
     *     attempts_count:int,
     *     last_attempt_at:string|null
     * }>
     */
    private function mapRankedEntries(Collection $rows): array
    {
        return $rows
            ->values()
            ->map(function (stdClass $row, int $index): array {
                $pointsTotal = round((float) $row->points_total, 2);
                $maxTotal = round((float) $row->max_total, 2);

                return [
                    'rank' => $index + 1,
                    'student_id' => (int) $row->student_id,
                    'nickname' => (string) $row->nickname,
                    'points_total' => $pointsTotal,
                    'max_total' => $maxTotal,
                    'percent' => $maxTotal > 0 ? round(($pointsTotal / $maxTotal) * 100, 2) : 0.0,
                    'attempts_count' => (int) $row->attempts_count,
                    'last_attempt_at' => $row->last_attempt_at !== null
                        ? CarbonImmutable::parse((string) $row->last_attempt_at)->toIso8601String()
                        : null,
                ];
            })
            ->all();
    }

    /**
     * @return array{
     *     class_id:int|null,
     *     period:string,
     *     start_date:string|null,
     *     end_date:string|null,
     *     computed_at:string,
     *     entries:array<int,array{
     *         rank:int,
     *         student_id:int,
     *         nickname:string,
     *         points_total:float,
     *         max_total:float,
     *         percent:float,
     *         attempts_count:int,
     *         last_attempt_at:string|null
     *     }>
     * }
     */
    private function snapshotOrCompute(
        ?int $classId,
        string $period,
        ?CarbonImmutable $startDate,
        ?CarbonImmutable $endDate,
    ): array {
        $snapshot = Leaderboard::query()
            ->where('class_id', $classId)
            ->where('period', $period)
            ->where('start_date', $startDate?->toDateString())
            ->where('end_date', $endDate?->toDateString())
            ->first();

        if ($snapshot !== null && is_array($snapshot->payload)) {
            return $snapshot->payload;
        }

        return $this->computeAndStore($classId, $period);
    }
}

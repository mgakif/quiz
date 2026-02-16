<?php

declare(strict_types=1);

namespace App\Domain\Leaderboards\Services;

use App\Domain\Leaderboard\ComputeLeaderboard;

class LeaderboardService
{
    public function __construct(private ComputeLeaderboard $computeLeaderboard)
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
        return $this->computeLeaderboard->getLeaderboard($classId, $period);
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
        return $this->computeLeaderboard->computeAndStore($classId, $period);
    }
}

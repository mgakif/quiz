<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Leaderboards\Services\LeaderboardService;
use App\Events\LeaderboardUpdated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ComputeLeaderboardJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public ?int $classId, public string $period)
    {
    }

    public function handle(LeaderboardService $leaderboardService): void
    {
        $payload = $leaderboardService->computeAndStore($this->classId, $this->period);

        event(new LeaderboardUpdated(
            classId: $this->classId,
            period: $this->period,
            payload: $payload,
        ));
    }
}

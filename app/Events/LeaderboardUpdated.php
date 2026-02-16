<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LeaderboardUpdated implements ShouldBroadcastNow
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public ?int $classId,
        public string $period,
        public array $payload,
    ) {
    }

    public function broadcastOn(): Channel
    {
        $scope = $this->classId === null ? 'global' : (string) $this->classId;

        return new Channel("leaderboards.{$scope}.{$this->period}");
    }

    public function broadcastAs(): string
    {
        return 'leaderboard.updated';
    }
}

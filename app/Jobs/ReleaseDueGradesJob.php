<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\AuditEvent;
use App\Models\Attempt;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ReleaseDueGradesJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        $dueAttempts = Attempt::query()
            ->where('release_at', '<=', now())
            ->where('grade_state', '!=', 'released')
            ->get();

        if ($dueAttempts->isEmpty()) {
            return;
        }

        Attempt::query()
            ->whereIn('id', $dueAttempts->pluck('id'))
            ->update([
                'grade_state' => 'released',
                'updated_at' => now(),
            ]);

        $classIds = $dueAttempts
            ->pluck('exam_id')
            ->map(fn (mixed $value): int => (int) $value)
            ->unique()
            ->values();

        foreach ($classIds as $classId) {
            foreach (['weekly', 'monthly', 'all_time'] as $period) {
                ComputeLeaderboardJob::dispatch($classId, $period);
            }
        }

        if (! Schema::hasTable('audit_events')) {
            return;
        }

        $auditRows = $dueAttempts
            ->map(fn (Attempt $attempt): array => [
                'uuid' => (string) Str::uuid(),
                'actor_id' => null,
                'actor_type' => 'system',
                'event_type' => 'grades_released',
                'entity_type' => 'attempt',
                'entity_id' => (string) $attempt->id,
                'meta' => json_encode([
                    'exam_id' => $attempt->exam_id,
                    'release_at' => $attempt->release_at?->toIso8601String(),
                ]) ?: '{}',
                'created_at' => now(),
            ])
            ->all();

        AuditEvent::query()->insert($auditRows);
    }
}

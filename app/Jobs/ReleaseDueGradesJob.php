<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Assessment;
use App\Models\Attempt;
use App\Models\AuditEvent;
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

        $recomputeTargets = Assessment::query()
            ->whereIn('legacy_exam_id', $dueAttempts->pluck('exam_id')->all())
            ->whereNotNull('term_id')
            ->get(['legacy_exam_id', 'term_id'])
            ->keyBy(fn (Assessment $assessment): int => (int) $assessment->legacy_exam_id);

        $dueAttempts
            ->map(function (Attempt $attempt) use ($recomputeTargets): ?string {
                if ($attempt->student_id === null) {
                    return null;
                }

                $assessment = $recomputeTargets->get((int) $attempt->exam_id);

                if (! $assessment instanceof Assessment) {
                    return null;
                }

                return sprintf('%s:%d', (string) $assessment->term_id, (int) $attempt->student_id);
            })
            ->filter()
            ->unique()
            ->values()
            ->each(function (string $pair): void {
                [$termId, $studentId] = explode(':', $pair);
                ComputeStudentTermGradeJob::dispatch($termId, (int) $studentId);
            });

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

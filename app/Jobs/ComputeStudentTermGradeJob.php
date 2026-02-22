<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Gradebook\ComputeStudentTermGrade;
use App\Models\AuditEvent;
use App\Models\Term;
use App\Models\User;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

class ComputeStudentTermGradeJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $termId, public int $studentId) {}

    public function handle(ComputeStudentTermGrade $computeStudentTermGrade): void
    {
        $lock = Cache::lock($this->lockKey(), 60);

        if (! $this->acquireLock($lock)) {
            return;
        }

        try {
            $term = Term::query()->find($this->termId);
            $student = User::query()->find($this->studentId);

            if (! $term instanceof Term || ! $student instanceof User || ! $student->isStudent()) {
                return;
            }

            $result = $computeStudentTermGrade->execute(
                term: $term,
                student: $student,
                classId: null,
                persist: true,
                recordAudit: false,
            );

            $entityId = sprintf('%s:%d', $term->id, $student->id);

            $alreadyRecorded = AuditEvent::query()
                ->where('event_type', 'term_grade_computed')
                ->where('entity_type', 'student_term_grade')
                ->where('entity_id', $entityId)
                ->exists();

            if ($alreadyRecorded) {
                return;
            }

            AuditEvent::query()->create([
                'actor_id' => null,
                'actor_type' => 'system',
                'event_type' => 'term_grade_computed',
                'entity_type' => 'student_term_grade',
                'entity_id' => $entityId,
                'meta' => [
                    'term_id' => $term->id,
                    'student_id' => $student->id,
                    'grade' => $result['computed_grade'] ?? null,
                    'missing_assessments_count' => $result['missing_assessments_count'] ?? null,
                ],
                'created_at' => now(),
            ]);
        } finally {
            $lock->release();
        }
    }

    private function lockKey(): string
    {
        return sprintf('gradebook:compute:%s:%d', $this->termId, $this->studentId);
    }

    private function acquireLock(Lock $lock): bool
    {
        return $lock->get();
    }
}

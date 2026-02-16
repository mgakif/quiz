<?php

declare(strict_types=1);

namespace App\Domain\Gradebook;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Models\StudentTermGrade;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class OverrideStudentTermGrade
{
    public function __construct(private RecordAuditEvent $recordAuditEvent)
    {
    }

    /**
     * @throws ValidationException
     */
    public function execute(StudentTermGrade $studentTermGrade, User $teacher, ?float $overriddenGrade, ?string $reason): StudentTermGrade
    {
        if (! $teacher->isTeacher()) {
            throw ValidationException::withMessages([
                'teacher' => 'Only teachers can override term grades.',
            ]);
        }

        if ($overriddenGrade !== null && blank($reason)) {
            throw ValidationException::withMessages([
                'override_reason' => 'Override reason is required when setting overridden grade.',
            ]);
        }

        $oldFinal = $studentTermGrade->finalGrade();
        $newOverride = $overriddenGrade !== null ? round($overriddenGrade, 2) : null;

        $studentTermGrade->update([
            'overridden_grade' => $newOverride,
            'override_reason' => $newOverride === null ? null : trim((string) $reason),
            'overridden_at' => $newOverride === null ? null : now(),
        ]);

        $fresh = $studentTermGrade->fresh();

        $this->recordAuditEvent->execute(
            actor: $teacher,
            actorType: 'teacher',
            eventType: 'term_grade_overridden',
            entityType: 'student_term_grade',
            entityId: $fresh?->id ?? $studentTermGrade->id,
            meta: [
                'term_id' => $studentTermGrade->term_id,
                'student_id' => $studentTermGrade->student_id,
                'old_final' => $oldFinal,
                'new_final' => $fresh?->finalGrade(),
                'reason' => $newOverride === null ? null : trim((string) $reason),
            ],
        );

        return $fresh ?? $studentTermGrade;
    }
}

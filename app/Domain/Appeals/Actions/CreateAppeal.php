<?php

declare(strict_types=1);

namespace App\Domain\Appeals\Actions;

use App\Domain\Attempts\Policies\GradeReleasePolicy;
use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Models\Appeal;
use App\Models\AttemptItem;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class CreateAppeal
{
    public function __construct(
        private GradeReleasePolicy $gradeReleasePolicy,
        private RecordAuditEvent $recordAuditEvent,
    ) {
    }

    /**
     * @throws ValidationException
     */
    public function execute(AttemptItem $attemptItem, User $student, string $reasonText): Appeal
    {
        $attemptItem->loadMissing('attempt');

        if (! $student->isStudent()) {
            throw ValidationException::withMessages([
                'student' => 'Only students can create appeals.',
            ]);
        }

        if ((int) $attemptItem->attempt->student_id !== (int) $student->id) {
            throw ValidationException::withMessages([
                'attempt_item_id' => 'You can only appeal your own responses.',
            ]);
        }

        if (! $this->gradeReleasePolicy->canStudentSeeGrades($attemptItem->attempt)) {
            throw ValidationException::withMessages([
                'attempt_item_id' => 'Appeal is available only after grades are released.',
            ]);
        }

        $windowHours = max(1, (int) config('appeals.window_hours', 72));
        $releasedAt = $attemptItem->attempt->release_at
            ?? $attemptItem->attempt->updated_at
            ?? $attemptItem->attempt->created_at;

        if ($releasedAt === null || now()->greaterThan($releasedAt->copy()->addHours($windowHours))) {
            throw ValidationException::withMessages([
                'attempt_item_id' => 'Appeal window is closed.',
            ]);
        }

        $activeAppealExists = Appeal::query()
            ->where('attempt_item_id', $attemptItem->id)
            ->whereIn('status', [Appeal::STATUS_OPEN, Appeal::STATUS_REVIEWING])
            ->exists();

        if ($activeAppealExists) {
            throw ValidationException::withMessages([
                'attempt_item_id' => 'There is already an active appeal for this item.',
            ]);
        }

        $appeal = Appeal::query()->create([
            'attempt_item_id' => $attemptItem->id,
            'student_id' => $student->id,
            'reason_text' => trim($reasonText),
            'status' => Appeal::STATUS_OPEN,
            'teacher_note' => null,
        ]);

        $this->recordAuditEvent->execute(
            actor: $student,
            actorType: 'student',
            eventType: 'appeal_created',
            entityType: 'appeal',
            entityId: $appeal->uuid,
            meta: [
                'attempt_item_id' => $attemptItem->id,
                'student_id' => $student->id,
            ],
        );

        return $appeal;
    }
}

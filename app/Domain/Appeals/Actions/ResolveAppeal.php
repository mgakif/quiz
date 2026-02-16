<?php

declare(strict_types=1);

namespace App\Domain\Appeals\Actions;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Regrade\Actions\ApplyDecisionAndRegrade;
use App\Models\Appeal;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ResolveAppeal
{
    public function __construct(
        private ApplyDecisionAndRegrade $applyDecisionAndRegrade,
        private RecordAuditEvent $recordAuditEvent,
    ) {
    }

    /**
     * @param  array<string, mixed>|null  $decision
     * @throws ValidationException
     */
    public function execute(
        Appeal $appeal,
        User $teacher,
        string $status,
        ?string $teacherNote = null,
        ?array $decision = null,
    ): Appeal {
        $appeal->loadMissing('attemptItem.questionVersion');

        if (! $teacher->isTeacher()) {
            throw ValidationException::withMessages([
                'teacher' => 'Only teachers can resolve appeals.',
            ]);
        }

        if (! in_array($status, [Appeal::STATUS_RESOLVED, Appeal::STATUS_REJECTED], true)) {
            throw ValidationException::withMessages([
                'status' => 'Status must be resolved or rejected.',
            ]);
        }

        if ($status === Appeal::STATUS_RESOLVED) {
            if (! is_array($decision)) {
                throw ValidationException::withMessages([
                    'decision' => 'A regrade decision is required when resolving an appeal.',
                ]);
            }
        }

        DB::transaction(function () use ($appeal, $status, $teacherNote, $decision, $teacher): void {
            if ($status === Appeal::STATUS_RESOLVED) {
                $this->applyDecisionAndRegrade->execute(
                    teacher: $teacher,
                    scope: (string) ($decision['scope'] ?? 'attempt_item'),
                    decisionType: (string) ($decision['decision_type'] ?? ''),
                    payload: is_array($decision['payload'] ?? null) ? $decision['payload'] : [],
                    attemptItem: $appeal->attemptItem,
                    questionVersion: $appeal->attemptItem?->questionVersion,
                );
            }

            $appeal->update([
                'status' => $status,
                'teacher_note' => blank($teacherNote) ? null : trim((string) $teacherNote),
            ]);
        });

        $this->recordAuditEvent->execute(
            actor: $teacher,
            actorType: 'teacher',
            eventType: $status === Appeal::STATUS_RESOLVED ? 'appeal_resolved' : 'appeal_rejected',
            entityType: 'appeal',
            entityId: $appeal->uuid,
            meta: [
                'attempt_item_id' => $appeal->attempt_item_id,
            ],
        );

        return $appeal->fresh();
    }
}

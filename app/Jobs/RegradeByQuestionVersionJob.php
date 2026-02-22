<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Models\AttemptItem;
use App\Models\RegradeDecision;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RegradeByQuestionVersionJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $regradeDecisionId, public int $questionVersionId) {}

    public function handle(RecordAuditEvent $recordAuditEvent): void
    {
        $decision = RegradeDecision::query()->find($this->regradeDecisionId);

        if ($decision === null) {
            return;
        }

        $payload = is_array($decision->payload) ? $decision->payload : [];
        $targetVersionId = is_numeric($payload['replaced_version_id'] ?? null)
            ? (int) $payload['replaced_version_id']
            : $this->questionVersionId;
        $count = AttemptItem::query()->where('question_version_id', $targetVersionId)->count();
        $systemUser = $this->resolveSystemUser();

        $recordAuditEvent->execute(
            actor: $systemUser,
            actorType: 'system',
            eventType: 'regrade_started',
            entityType: 'question_version',
            entityId: (string) $targetVersionId,
            meta: [
                'decision_id' => $decision->id,
                'attempt_item_count' => $count,
            ],
        );

        AttemptItem::query()
            ->where('question_version_id', $targetVersionId)
            ->select('id')
            ->orderBy('id')
            ->chunkById(200, function ($items): void {
                foreach ($items as $item) {
                    RegradeAttemptItemJob::dispatch($this->regradeDecisionId, (int) $item->id);
                }
            });

        AttemptItem::query()
            ->where('attempt_items.question_version_id', $targetVersionId)
            ->join('attempts', 'attempts.id', '=', 'attempt_items.attempt_id')
            ->join('assessments', 'assessments.legacy_exam_id', '=', 'attempts.exam_id')
            ->whereNotNull('assessments.term_id')
            ->selectRaw('assessments.term_id as term_id, attempts.student_id as student_id')
            ->distinct()
            ->get()
            ->each(function (object $row): void {
                $termId = (string) $row->term_id;
                $studentId = (int) $row->student_id;

                if ($termId === '' || $studentId <= 0) {
                    return;
                }

                ComputeStudentTermGradeJob::dispatch($termId, $studentId);
            });

        $recordAuditEvent->execute(
            actor: $systemUser,
            actorType: 'system',
            eventType: 'regrade_finished',
            entityType: 'question_version',
            entityId: (string) $targetVersionId,
            meta: [
                'decision_id' => $decision->id,
                'attempt_item_count' => $count,
            ],
        );
    }

    private function resolveSystemUser(): User
    {
        return User::query()->firstOrCreate(
            ['email' => 'system@local'],
            [
                'name' => 'System',
                'password' => 'system-password',
                'role' => User::ROLE_TEACHER,
            ],
        );
    }
}

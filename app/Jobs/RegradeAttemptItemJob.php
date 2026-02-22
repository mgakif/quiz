<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Models\AiGrading;
use App\Models\AttemptItem;
use App\Models\QuestionVersion;
use App\Models\RegradeDecision;
use App\Models\RubricScore;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RegradeAttemptItemJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $regradeDecisionId, public int $attemptItemId) {}

    public function handle(RecordAuditEvent $recordAuditEvent): void
    {
        $decision = RegradeDecision::query()->find($this->regradeDecisionId);
        $attemptItem = AttemptItem::query()
            ->with(['questionVersion.question', 'response', 'rubricScore'])
            ->find($this->attemptItemId);

        if ($decision === null || $attemptItem === null) {
            return;
        }

        $systemUser = $this->resolveSystemUser();
        $payload = is_array($decision->payload) ? $decision->payload : [];

        $recordAuditEvent->execute(
            actor: $systemUser,
            actorType: 'system',
            eventType: 'regrade_started',
            entityType: 'attempt_item',
            entityId: (string) $attemptItem->id,
            meta: [
                'decision_id' => $decision->id,
                'decision_type' => $decision->decision_type,
            ],
        );

        match ($decision->decision_type) {
            'answer_key_change' => $this->applyAnswerKeyChange($attemptItem, $payload, $systemUser),
            'rubric_change' => $this->markNeedsTeacherReview($attemptItem),
            'partial_credit' => $this->applyPartialCredit($attemptItem, $payload, $systemUser, $recordAuditEvent),
            'void_question' => $this->applyVoidQuestion($attemptItem, $payload, $systemUser),
            default => null,
        };

        $recordAuditEvent->execute(
            actor: $systemUser,
            actorType: 'system',
            eventType: 'regrade_finished',
            entityType: 'attempt_item',
            entityId: (string) $attemptItem->id,
            meta: [
                'decision_id' => $decision->id,
                'decision_type' => $decision->decision_type,
            ],
        );

        $attemptItem->loadMissing('attempt.assessment');
        $termId = (string) ($attemptItem->attempt?->assessment?->term_id ?? '');
        $studentId = (int) ($attemptItem->attempt?->student_id ?? 0);

        if ($termId !== '' && $studentId > 0) {
            ComputeStudentTermGradeJob::dispatch($termId, $studentId);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function applyAnswerKeyChange(AttemptItem $attemptItem, array $payload, User $systemUser): void
    {
        $newVersion = null;

        if (is_numeric($payload['new_version_id'] ?? null)) {
            $newVersion = QuestionVersion::query()->find((int) $payload['new_version_id']);
        }

        $gradingVersion = $newVersion ?? $attemptItem->questionVersion;
        $questionType = (string) $gradingVersion->type;

        if (! in_array($questionType, ['mcq', 'matching'], true)) {
            $this->markNeedsTeacherReview($attemptItem);

            return;
        }

        $maxPoints = round((float) $attemptItem->max_points, 2);
        $awardedPoints = 0.0;

        if ($questionType === 'mcq') {
            $correctChoice = (string) data_get($gradingVersion->answer_key, 'correct_choice_id', '');
            $studentChoice = (string) data_get($attemptItem->response?->response_payload, 'choice_id', data_get($attemptItem->response?->response_payload, 'selected_choice_id', data_get($attemptItem->response?->response_payload, 'answer', '')));
            $awardedPoints = $correctChoice !== '' && $correctChoice === $studentChoice ? $maxPoints : 0.0;
        }

        if ($questionType === 'matching') {
            $expected = data_get($gradingVersion->answer_key, 'answer_key', []);
            $actual = data_get($attemptItem->response?->response_payload, 'answer_key', data_get($attemptItem->response?->response_payload, 'matching', []));
            $expectedArray = is_array($expected) ? $expected : [];
            $actualArray = is_array($actual) ? $actual : [];

            $total = count($expectedArray);
            $correct = collect($expectedArray)
                ->filter(fn (mixed $value, mixed $key): bool => array_key_exists((string) $key, $actualArray) && $actualArray[(string) $key] === $value)
                ->count();

            $awardedPoints = $total > 0 ? round(($correct / $total) * $maxPoints, 2) : 0.0;
        }

        RubricScore::query()->updateOrCreate(
            ['attempt_item_id' => $attemptItem->id],
            [
                'scores' => [[
                    'criterion' => 'auto_grade',
                    'points' => $awardedPoints,
                    'max_points' => $maxPoints,
                ]],
                'total_points' => $awardedPoints,
                'graded_by' => $systemUser->id,
                'graded_at' => now(),
                'override_reason' => 'Regraded after answer key change.',
                'is_draft' => false,
            ],
        );
    }

    private function markNeedsTeacherReview(AttemptItem $attemptItem): void
    {
        $existingAi = AiGrading::query()->where('attempt_item_id', $attemptItem->id)->first();
        $existingFlags = is_array($existingAi?->flags) ? $existingAi->flags : [];

        if (! in_array('needs_teacher_review', $existingFlags, true)) {
            $existingFlags[] = 'needs_teacher_review';
        }

        AiGrading::query()->updateOrCreate(
            ['attempt_item_id' => $attemptItem->id],
            [
                'response_json' => is_array($existingAi?->response_json) ? $existingAi->response_json : [],
                'confidence' => (float) ($existingAi?->confidence ?? 0),
                'flags' => $existingFlags,
                'status' => 'needs_review',
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function applyPartialCredit(
        AttemptItem $attemptItem,
        array $payload,
        User $systemUser,
        RecordAuditEvent $recordAuditEvent,
    ): void {
        $reason = trim((string) ($payload['reason'] ?? ''));

        if ($reason === '') {
            return;
        }

        $maxPoints = round((float) $attemptItem->max_points, 2);
        $newPoints = (float) $payload['new_points'];
        $newPoints = max(0, min($maxPoints, round($newPoints, 2)));

        RubricScore::query()->updateOrCreate(
            ['attempt_item_id' => $attemptItem->id],
            [
                'scores' => [[
                    'criterion' => 'partial_credit',
                    'points' => $newPoints,
                    'max_points' => $maxPoints,
                    'reason' => $reason,
                ]],
                'total_points' => $newPoints,
                'graded_by' => $systemUser->id,
                'graded_at' => now(),
                'override_reason' => $reason,
                'is_draft' => false,
            ],
        );

        $recordAuditEvent->execute(
            actor: $systemUser,
            actorType: 'system',
            eventType: 'manual_override',
            entityType: 'attempt_item',
            entityId: (string) $attemptItem->id,
            meta: [
                'new_points' => $newPoints,
                'reason' => $reason,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function applyVoidQuestion(AttemptItem $attemptItem, array $payload, User $systemUser): void
    {
        $mode = (string) ($payload['mode'] ?? 'drop_from_total');
        $maxPointsBefore = round((float) $attemptItem->max_points, 2);
        $awardedPoints = $mode === 'give_full' ? $maxPointsBefore : 0.0;

        if ($mode === 'drop_from_total') {
            $attemptItem->update(['max_points' => 0]);
        }

        RubricScore::query()->updateOrCreate(
            ['attempt_item_id' => $attemptItem->id],
            [
                'scores' => [[
                    'criterion' => 'void_question',
                    'mode' => $mode,
                    'points' => $awardedPoints,
                    'max_points_before' => $maxPointsBefore,
                ]],
                'total_points' => $awardedPoints,
                'graded_by' => $systemUser->id,
                'graded_at' => now(),
                'override_reason' => 'Question voided by teacher decision.',
                'is_draft' => false,
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

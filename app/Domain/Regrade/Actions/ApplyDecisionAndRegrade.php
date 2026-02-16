<?php

declare(strict_types=1);

namespace App\Domain\Regrade\Actions;

use App\Jobs\ComputeLeaderboardJob;
use App\Jobs\RegradeAttemptItemJob;
use App\Jobs\RegradeByQuestionVersionJob;
use App\Models\AttemptItem;
use App\Models\QuestionVersion;
use App\Models\RegradeDecision;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApplyDecisionAndRegrade
{
    public function __construct()
    {
    }

    /**
     * @param  array<string, mixed>  $payload
     * @throws ValidationException
     */
    public function execute(
        User $teacher,
        string $scope,
        string $decisionType,
        array $payload,
        ?AttemptItem $attemptItem = null,
        ?QuestionVersion $questionVersion = null,
    ): RegradeDecision {
        if (! $teacher->isTeacher()) {
            throw ValidationException::withMessages([
                'teacher' => 'Only teachers can create regrade decisions.',
            ]);
        }

        if (! in_array($scope, ['attempt_item', 'question_version'], true)) {
            throw ValidationException::withMessages([
                'scope' => 'Invalid regrade scope.',
            ]);
        }

        if (! in_array($decisionType, ['answer_key_change', 'rubric_change', 'partial_credit', 'void_question'], true)) {
            throw ValidationException::withMessages([
                'decision_type' => 'Invalid decision type.',
            ]);
        }

        if ($scope === 'attempt_item' && $attemptItem === null) {
            throw ValidationException::withMessages([
                'attempt_item_id' => 'Attempt item is required for attempt_item scope.',
            ]);
        }

        if ($scope === 'question_version' && $questionVersion === null) {
            throw ValidationException::withMessages([
                'question_version_id' => 'Question version is required for question version scope.',
            ]);
        }

        return DB::transaction(function () use (
            $teacher,
            $scope,
            $decisionType,
            $payload,
            $attemptItem,
            $questionVersion,
        ): RegradeDecision {
            $questionVersion = $questionVersion ?? $attemptItem?->questionVersion()->first();
            $this->assertPayload($decisionType, $payload);

            $decisionPayload = $payload;

            if (in_array($decisionType, ['answer_key_change', 'rubric_change'], true)) {
                if (! $questionVersion instanceof QuestionVersion) {
                    throw ValidationException::withMessages([
                        'question_version_id' => 'Question version is required for this decision type.',
                    ]);
                }

                $newVersion = $this->createReplacementVersion($questionVersion, $decisionType, $payload);
                $decisionPayload['replaced_version_id'] = $questionVersion->id;
                $decisionPayload['new_version_id'] = $newVersion->id;
            }

            $decision = RegradeDecision::query()->create([
                'scope' => $scope,
                'attempt_item_id' => $scope === 'attempt_item' ? $attemptItem?->id : null,
                'question_version_id' => $scope === 'question_version'
                    ? $questionVersion?->id
                    : ($attemptItem?->question_version_id),
                'decision_type' => $decisionType,
                'payload' => $decisionPayload,
                'decided_by' => $teacher->id,
                'decided_at' => now(),
                'created_at' => now(),
            ]);

            if ($scope === 'attempt_item') {
                RegradeAttemptItemJob::dispatch($decision->id, (int) $attemptItem?->id);
            } else {
                RegradeByQuestionVersionJob::dispatch($decision->id, (int) $questionVersion?->id);
            }

            foreach ($this->resolveAffectedClassIds($scope, $attemptItem, $questionVersion) as $classId) {
                foreach (['weekly', 'monthly', 'all_time'] as $period) {
                    ComputeLeaderboardJob::dispatch($classId, $period);
                }
            }

            return $decision;
        });
    }

    public function previewAffectedAttemptCount(string $scope, ?AttemptItem $attemptItem, ?QuestionVersion $questionVersion): int
    {
        if ($scope === 'attempt_item') {
            return $attemptItem === null ? 0 : 1;
        }

        if ($questionVersion === null) {
            return 0;
        }

        return AttemptItem::query()
            ->where('question_version_id', $questionVersion->id)
            ->count();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @throws ValidationException
     */
    private function assertPayload(string $decisionType, array $payload): void
    {
        if ($decisionType === 'answer_key_change' && ! is_array($payload['new_answer_key'] ?? null)) {
            throw ValidationException::withMessages([
                'payload.new_answer_key' => 'new_answer_key is required for answer_key_change.',
            ]);
        }

        if ($decisionType === 'rubric_change' && ! is_array($payload['new_rubric'] ?? null)) {
            throw ValidationException::withMessages([
                'payload.new_rubric' => 'new_rubric is required for rubric_change.',
            ]);
        }

        if ($decisionType === 'partial_credit') {
            if (! is_numeric($payload['new_points'] ?? null)) {
                throw ValidationException::withMessages([
                    'payload.new_points' => 'new_points is required for partial_credit.',
                ]);
            }

            if (blank($payload['reason'] ?? null)) {
                throw ValidationException::withMessages([
                    'payload.reason' => 'reason is required for partial_credit.',
                ]);
            }
        }

        if ($decisionType === 'void_question') {
            $mode = (string) ($payload['mode'] ?? '');

            if (! in_array($mode, ['give_full', 'drop_from_total'], true)) {
                throw ValidationException::withMessages([
                    'payload.mode' => 'void_question mode must be give_full or drop_from_total.',
                ]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function createReplacementVersion(QuestionVersion $questionVersion, string $decisionType, array $payload): QuestionVersion
    {
        $attributes = [
            'type' => $questionVersion->type,
            'payload' => $questionVersion->payload,
            'answer_key' => $questionVersion->answer_key,
            'rubric' => $questionVersion->rubric,
        ];

        if ($decisionType === 'answer_key_change') {
            $attributes['answer_key'] = $payload['new_answer_key'];
        }

        if ($decisionType === 'rubric_change') {
            $attributes['rubric'] = $payload['new_rubric'];
        }

        return $questionVersion->question->createVersion($attributes);
    }

    /**
     * @return array<int, int>
     */
    private function resolveAffectedClassIds(
        string $scope,
        ?AttemptItem $attemptItem,
        ?QuestionVersion $questionVersion,
    ): array {
        if ($scope === 'attempt_item' && $attemptItem !== null) {
            $attemptItem->loadMissing('attempt:id,exam_id');
            $examId = (int) ($attemptItem->attempt?->exam_id ?? 0);

            return $examId > 0 ? [$examId] : [];
        }

        if ($questionVersion === null) {
            return [];
        }

        return AttemptItem::query()
            ->where('question_version_id', $questionVersion->id)
            ->join('attempts', 'attempts.id', '=', 'attempt_items.attempt_id')
            ->distinct()
            ->pluck('attempts.exam_id')
            ->map(fn (mixed $value): int => (int) $value)
            ->filter(fn (int $examId): bool => $examId > 0)
            ->values()
            ->all();
    }
}

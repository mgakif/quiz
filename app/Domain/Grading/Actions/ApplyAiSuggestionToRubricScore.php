<?php

declare(strict_types=1);

namespace App\Domain\Grading\Actions;

use App\Models\AttemptItem;
use App\Models\RubricScore;
use App\Models\User;

class ApplyAiSuggestionToRubricScore
{
    public function execute(AttemptItem $attemptItem): ?RubricScore
    {
        $aiGrading = $attemptItem->aiGrading;

        if ($aiGrading === null || ! in_array($aiGrading->status, ['draft', 'needs_review'], true)) {
            return null;
        }

        $responseJson = is_array($aiGrading->response_json) ? $aiGrading->response_json : [];
        $criteriaScores = is_array($responseJson['criteria_scores'] ?? null) ? $responseJson['criteria_scores'] : [];

        $mappedScores = collect($criteriaScores)
            ->map(fn (array $criterion): array => [
                'criterion' => (string) ($criterion['criterion_id'] ?? ''),
                'points' => (float) ($criterion['score'] ?? 0),
                'max_points' => (float) ($criterion['max_score'] ?? 0),
                'reasoning' => (string) ($criterion['reasoning'] ?? ''),
                'evidence' => $criterion['evidence'] ?? [],
            ])
            ->filter(fn (array $score): bool => $score['criterion'] !== '')
            ->values()
            ->all();

        $systemUser = User::query()->firstOrCreate(
            ['email' => 'system@local'],
            [
                'name' => 'System',
                'password' => 'system-password',
                'role' => User::ROLE_TEACHER,
            ],
        );

        $rubricScore = RubricScore::query()->updateOrCreate(
            ['attempt_item_id' => $attemptItem->id],
            [
                'scores' => $mappedScores,
                'total_points' => round((float) ($responseJson['total_points'] ?? 0), 2),
                'graded_by' => $systemUser->id,
                'graded_at' => now(),
                'override_reason' => 'AI suggestion draft. Teacher approval required.',
                'is_draft' => true,
            ],
        );

        $aiGrading->update(['status' => 'approved']);

        return $rubricScore;
    }
}

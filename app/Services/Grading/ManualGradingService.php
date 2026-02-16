<?php

declare(strict_types=1);

namespace App\Services\Grading;

use App\Models\AttemptItem;
use App\Models\RubricScore;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManualGradingService
{
    /**
     * @param  array<int, array{criterion:string, points:int|float|string}>  $scores
     * @throws ValidationException
     */
    public function gradeAttemptItem(
        AttemptItem $attemptItem,
        array $scores,
        float $totalPoints,
        int $gradedBy,
        ?string $overrideReason = null,
    ): RubricScore {
        return DB::transaction(function () use ($attemptItem, $scores, $totalPoints, $gradedBy, $overrideReason): RubricScore {
            $existing = $attemptItem->rubricScore()->first();

            if (
                $existing !== null
                && (round((float) $existing->total_points, 2) !== round($totalPoints, 2))
                && blank($overrideReason)
            ) {
                throw ValidationException::withMessages([
                    'override_reason' => 'Override reason is required when changing total points.',
                ]);
            }

            return RubricScore::query()->updateOrCreate(
                ['attempt_item_id' => $attemptItem->id],
                [
                    'scores' => $scores,
                    'total_points' => round($totalPoints, 2),
                    'graded_by' => $gradedBy,
                    'graded_at' => now(),
                    'override_reason' => blank($overrideReason) ? null : $overrideReason,
                    'is_draft' => false,
                ],
            );
        });
    }
}

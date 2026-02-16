<?php

declare(strict_types=1);

namespace App\Http\Controllers\Student;

use App\Domain\Attempts\GradeRelease;
use App\Http\Controllers\Controller;
use App\Models\Attempt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttemptResultController extends Controller
{
    public function __invoke(Request $request, Attempt $attempt, GradeRelease $gradeRelease): JsonResponse
    {
        abort_unless((int) $attempt->student_id === (int) $request->user()?->id, 403);

        if (! $gradeRelease->canStudentSeeDetails($attempt)) {
            $releaseMessage = $attempt->release_at !== null
                ? 'Notlar '.$attempt->release_at->format('Y-m-d H:i').' tarihinde aciklanacak.'
                : 'Notlar henuz aciklanmadi.';

            return response()->json([
                'attempt_id' => $attempt->id,
                'status' => $attempt->grade_state,
                'grade_state' => $attempt->grade_state,
                'release_at' => $attempt->release_at?->toIso8601String(),
                'submitted_at' => $attempt->submitted_at?->toIso8601String(),
                'message' => $releaseMessage,
            ]);
        }

        $attempt->load([
            'items.rubricScore',
            'items.aiGrading',
            'items.appeals',
        ]);

        return response()->json([
            'attempt_id' => $attempt->id,
            'status' => $attempt->grade_state,
            'grade_state' => $attempt->grade_state,
            'release_at' => $attempt->release_at?->toIso8601String(),
            'submitted_at' => $attempt->submitted_at?->toIso8601String(),
            'total_points' => round((float) $attempt->items->sum(fn ($item): float => (float) ($item->rubricScore?->total_points ?? 0)), 2),
            'items' => $attempt->items
                ->sortBy('order')
                ->values()
                ->map(fn ($item): array => [
                    'attempt_item_id' => $item->id,
                    'score' => $item->rubricScore?->total_points !== null ? (float) $item->rubricScore->total_points : null,
                    'feedback' => data_get($item->aiGrading?->response_json, 'feedback'),
                    'rubric_scores' => $item->rubricScore?->scores,
                    'ai_gradings' => $item->aiGrading?->response_json,
                    'appeal_count' => $item->appeals->count(),
                    'appeals' => $item->appeals
                        ->sortByDesc('created_at')
                        ->values()
                        ->map(fn ($appeal): array => [
                            'uuid' => $appeal->uuid,
                            'status' => $appeal->status,
                            'created_at' => $appeal->created_at?->toIso8601String(),
                        ])
                        ->all(),
                ])
                ->all(),
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Domain\Attempts\GradeRelease;
use App\Http\Controllers\Controller;
use App\Models\Attempt;
use App\Models\AttemptResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicAttemptController extends Controller
{
    public function submit(Request $request, Attempt $attempt): JsonResponse
    {
        $this->assertPublicAttempt($attempt);

        if ($attempt->submitted_at !== null) {
            return response()->json([
                'message' => 'Attempt already submitted.',
            ], 422);
        }

        $validated = $request->validate([
            'responses' => ['nullable', 'array'],
            'responses.*.attempt_item_id' => ['required_with:responses', 'integer', 'exists:attempt_items,id'],
            'responses.*.response_payload' => ['required_with:responses', 'array'],
        ]);

        foreach (($validated['responses'] ?? []) as $response) {
            $attemptItemId = (int) $response['attempt_item_id'];

            if (! $attempt->items()->where('id', $attemptItemId)->exists()) {
                continue;
            }

            AttemptResponse::query()->updateOrCreate(
                ['attempt_item_id' => $attemptItemId],
                [
                    'response_payload' => $response['response_payload'],
                    'submitted_at' => now(),
                ],
            );
        }

        $attempt->update([
            'submitted_at' => now(),
            'grade_state' => 'graded',
        ]);

        return response()->json([
            'attempt_id' => $attempt->id,
            'status' => 'submitted',
            'submitted_at' => $attempt->fresh()?->submitted_at?->toIso8601String(),
        ]);
    }

    public function result(Attempt $attempt, GradeRelease $gradeRelease): JsonResponse
    {
        $this->assertPublicAttempt($attempt);

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
                ])
                ->all(),
        ]);
    }

    private function assertPublicAttempt(Attempt $attempt): void
    {
        abort_if($attempt->guest_id === null, 404);
    }
}

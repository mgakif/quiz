<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Attempt;
use App\Models\GuestUser;
use App\Models\PublicExamLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PublicExamController extends Controller
{
    public function show(string $token): JsonResponse
    {
        $link = $this->resolveActiveLink($token);

        return response()->json([
            'token' => $link->token,
            'exam_id' => $link->exam_id,
            'title' => $link->exam?->title,
            'is_enabled' => $link->is_enabled,
            'expires_at' => $link->expires_at?->toIso8601String(),
            'max_attempts' => $link->max_attempts,
            'require_name' => $link->require_name,
            'start_url' => url('/public/'.$link->token.'/start'),
        ]);
    }

    public function start(Request $request, string $token): JsonResponse
    {
        $link = $this->resolveActiveLink($token);

        $validated = $request->validate([
            'display_name' => ['nullable', 'string', 'max:255'],
        ]);

        $displayName = trim((string) ($validated['display_name'] ?? ''));

        if ($link->require_name && $displayName === '') {
            return response()->json([
                'message' => 'display_name is required for this public exam link.',
            ], 422);
        }

        $attempt = DB::transaction(function () use ($link, $displayName): Attempt {
            $freshLink = PublicExamLink::query()->lockForUpdate()->findOrFail($link->id);

            if (! $freshLink->isActive()) {
                throw new HttpException(403, 'Public exam link is not active.');
            }

            $guest = GuestUser::query()->create([
                'display_name' => $displayName !== '' ? $displayName : null,
            ]);

            return Attempt::query()->create([
                'exam_id' => (int) $freshLink->exam_id,
                'student_id' => null,
                'guest_id' => $guest->id,
                'public_exam_link_id' => $freshLink->id,
                'started_at' => now(),
                'grade_state' => 'pending',
            ]);
        });

        return response()->json([
            'attempt_id' => $attempt->id,
            'guest_id' => $attempt->guest_id,
            'exam_id' => $attempt->exam_id,
            'started_at' => $attempt->started_at?->toIso8601String(),
            'submit_url' => url('/public/attempts/'.$attempt->id.'/submit'),
            'result_url' => url('/public/attempts/'.$attempt->id.'/result'),
        ], 201);
    }

    private function resolveActiveLink(string $token): PublicExamLink
    {
        $link = PublicExamLink::query()
            ->with('exam')
            ->where('token', $token)
            ->first();

        if (! $link instanceof PublicExamLink || ! $link->isActive()) {
            abort(404);
        }

        return $link;
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Student;

use App\Domain\Leaderboards\Services\LeaderboardService;
use App\Http\Controllers\Controller;
use App\Models\Attempt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LeaderboardController extends Controller
{
    public function index(Request $request): View
    {
        $classOptions = Attempt::query()
            ->where('student_id', (int) $request->user()?->id)
            ->select('exam_id')
            ->distinct()
            ->orderBy('exam_id')
            ->pluck('exam_id')
            ->all();

        return view('student.leaderboard', [
            'classOptions' => $classOptions,
            'defaultClassId' => $classOptions[0] ?? null,
        ]);
    }

    public function show(Request $request, LeaderboardService $leaderboardService): JsonResponse
    {
        $validated = validator($request->all(), [
            'class_id' => ['required', 'integer', 'min:1'],
            'period' => ['required', 'in:weekly,monthly,all_time'],
        ])->validate();

        $classId = (int) $validated['class_id'];
        $period = (string) $validated['period'];
        $studentId = (int) $request->user()?->id;

        $isStudentInClass = Attempt::query()
            ->where('student_id', $studentId)
            ->where('exam_id', $classId)
            ->exists();

        abort_unless($isStudentInClass, 403);

        $payload = $leaderboardService->getLeaderboard($classId, $period);

        $payload['entries'] = collect($payload['entries'] ?? [])
            ->map(function (array $entry): array {
                unset($entry['student_id']);

                return $entry;
            })
            ->values()
            ->all();

        return response()->json($payload);
    }
}

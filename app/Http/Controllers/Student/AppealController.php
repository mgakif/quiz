<?php

declare(strict_types=1);

namespace App\Http\Controllers\Student;

use App\Domain\Appeals\Actions\CreateAppeal;
use App\Http\Controllers\Controller;
use App\Http\Requests\Student\CreateAppealRequest;
use App\Models\AttemptItem;
use Illuminate\Http\JsonResponse;

class AppealController extends Controller
{
    public function store(CreateAppealRequest $request, AttemptItem $attemptItem, CreateAppeal $createAppeal): JsonResponse
    {
        $attemptItem->loadMissing('attempt');

        abort_unless((int) $attemptItem->attempt->student_id === (int) $request->user()?->id, 403);

        $appeal = $createAppeal->execute(
            attemptItem: $attemptItem,
            student: $request->user(),
            reasonText: (string) $request->validated('reason_text'),
        );

        return response()->json([
            'uuid' => $appeal->uuid,
            'status' => $appeal->status,
            'created_at' => $appeal->created_at?->toIso8601String(),
        ], 201);
    }
}

<?php

declare(strict_types=1);

use App\Http\Controllers\Student\AttemptResultController;
use App\Http\Controllers\Student\AppealController;
use App\Http\Controllers\Student\LeaderboardController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function (): void {
    Route::get('/student/attempts/{attempt}/results', AttemptResultController::class);
    Route::post('/student/attempt-items/{attemptItem}/appeals', [AppealController::class, 'store']);
    Route::get('/student/leaderboard', [LeaderboardController::class, 'show']);
});

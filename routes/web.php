<?php

use App\Http\Controllers\Public\PublicAttemptController;
use App\Http\Controllers\Public\PublicExamController;
use App\Http\Controllers\Student\LeaderboardController;
use App\Http\Controllers\Student\MyGradesController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/leaderboard', [LeaderboardController::class, 'index']);
    Route::get('/student/leaderboard', [LeaderboardController::class, 'index']);
    Route::get('/student/grades', MyGradesController::class);
});

Route::prefix('public')
    ->middleware('throttle:30,1')
    ->group(function (): void {
        Route::get('/{token}', [PublicExamController::class, 'show']);
        Route::post('/{token}/start', [PublicExamController::class, 'start']);
        Route::post('/attempts/{attempt}/submit', [PublicAttemptController::class, 'submit']);
        Route::get('/attempts/{attempt}/result', [PublicAttemptController::class, 'result']);
    });

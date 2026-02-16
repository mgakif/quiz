<?php

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

<?php

use App\Jobs\ComputeLeaderboardJob;
use App\Jobs\ReleaseDueGradesJob;
use App\Jobs\UpdateQuestionStatsJob;
use App\Models\Attempt;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('attempts:release-due-grades', function (): void {
    dispatch_sync(new ReleaseDueGradesJob());

    $this->info('Due grades have been released.');
})->purpose('Release grades for attempts that reached release_at');

Artisan::command('analytics:update-question-stats', function (): void {
    dispatch_sync(new UpdateQuestionStatsJob());

    $this->info('Question stats updated.');
})->purpose('Update question usage and correct rate statistics');

Artisan::command('leaderboard:compute {period} {class_id?}', function (): void {
    $classIdArgument = $this->argument('class_id');
    $classId = $classIdArgument !== null ? (int) $classIdArgument : null;
    $period = (string) $this->argument('period');

    dispatch_sync(new ComputeLeaderboardJob($classId, $period));

    $scope = $classId === null ? 'global' : "class {$classId}";
    $this->info("Leaderboard computed for {$scope}, period {$period}.");
})->purpose('Compute leaderboard snapshot and cache');

Schedule::job(new ReleaseDueGradesJob())->everyMinute();
Schedule::job(new UpdateQuestionStatsJob())->hourly();
Schedule::call(function (): void {
    $classIds = Attempt::query()->select('exam_id')->distinct()->pluck('exam_id');

    foreach ($classIds as $classId) {
        foreach (['weekly', 'monthly', 'all_time'] as $period) {
            ComputeLeaderboardJob::dispatch((int) $classId, $period);
        }
    }

    foreach (['weekly', 'monthly', 'all_time'] as $period) {
        ComputeLeaderboardJob::dispatch(null, $period);
    }
})->everyTenMinutes();

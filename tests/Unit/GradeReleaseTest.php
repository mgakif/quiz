<?php

declare(strict_types=1);

use App\Domain\Attempts\GradeRelease;
use App\Models\Attempt;

it('determines release status correctly from grade_state and release_at', function (): void {
    $service = new GradeRelease();

    $releasedByState = new Attempt([
        'grade_state' => 'released',
        'release_at' => now()->addHour(),
        'submitted_at' => now(),
    ]);

    $releasedByTime = new Attempt([
        'grade_state' => 'graded',
        'release_at' => now()->subMinute(),
        'submitted_at' => now(),
    ]);

    $notReleased = new Attempt([
        'grade_state' => 'graded',
        'release_at' => now()->addMinute(),
        'submitted_at' => now(),
    ]);

    $releasedButNotSubmitted = new Attempt([
        'grade_state' => 'released',
        'release_at' => now()->subMinute(),
        'submitted_at' => null,
    ]);

    expect($service->isReleased($releasedByState))->toBeTrue()
        ->and($service->isReleased($releasedByTime))->toBeTrue()
        ->and($service->isReleased($notReleased))->toBeFalse()
        ->and($service->canStudentSeeDetails($releasedByState))->toBeTrue()
        ->and($service->canStudentSeeDetails($releasedByTime))->toBeTrue()
        ->and($service->canStudentSeeDetails($notReleased))->toBeFalse()
        ->and($service->canStudentSeeDetails($releasedButNotSubmitted))->toBeFalse();
});

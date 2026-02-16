<?php

declare(strict_types=1);

namespace App\Domain\Attempts;

use App\Models\Attempt;
use Illuminate\Database\Eloquent\Builder;

class GradeRelease
{
    public function isReleased(Attempt $attempt): bool
    {
        if ($attempt->grade_state === 'released') {
            return true;
        }

        return $attempt->release_at !== null && $attempt->release_at->lessThanOrEqualTo(now());
    }

    public function canStudentSeeDetails(Attempt $attempt): bool
    {
        return $this->isReleased($attempt) && $attempt->submitted_at !== null;
    }

    public function applyReleasedScope(Builder $query): Builder
    {
        return $query->where(function (Builder $attemptQuery): void {
            $attemptQuery
                ->where('grade_state', 'released')
                ->orWhere(function (Builder $releaseAtQuery): void {
                    $releaseAtQuery
                        ->whereNotNull('release_at')
                        ->where('release_at', '<=', now());
                });
        });
    }
}

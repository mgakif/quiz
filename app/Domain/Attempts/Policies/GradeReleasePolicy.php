<?php

declare(strict_types=1);

namespace App\Domain\Attempts\Policies;

use App\Domain\Attempts\GradeRelease;
use App\Models\Attempt;
use Illuminate\Database\Eloquent\Builder;

class GradeReleasePolicy
{
    public function __construct(private GradeRelease $gradeRelease)
    {
    }

    public function canStudentSeeGrades(Attempt $attempt): bool
    {
        return $this->gradeRelease->canStudentSeeDetails($attempt);
    }

    public function applyVisibilityScope(Builder $query): Builder
    {
        return $this->gradeRelease->applyReleasedScope($query);
    }
}

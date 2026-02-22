<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Exam;
use App\Models\User;

class ExamPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isTeacher();
    }

    public function view(User $user, Exam $exam): bool
    {
        return $user->isTeacher();
    }

    public function create(User $user): bool
    {
        return $user->isTeacher();
    }

    public function update(User $user, Exam $exam): bool
    {
        return $user->isTeacher();
    }

    public function delete(User $user, Exam $exam): bool
    {
        return $user->isTeacher();
    }
}

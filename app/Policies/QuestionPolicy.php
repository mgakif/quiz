<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Question;
use App\Models\User;

class QuestionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isTeacher();
    }

    public function view(User $user, Question $question): bool
    {
        return $user->isTeacher();
    }

    public function create(User $user): bool
    {
        return $user->isTeacher();
    }

    public function update(User $user, Question $question): bool
    {
        return $user->isTeacher();
    }

    public function delete(User $user, Question $question): bool
    {
        return $user->isTeacher();
    }

    public function archive(User $user, Question $question): bool
    {
        return $user->isTeacher();
    }

    public function deprecate(User $user, Question $question): bool
    {
        return $user->isTeacher();
    }

    public function createVersion(User $user, Question $question): bool
    {
        return $user->isTeacher();
    }
}

<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PublicExamLink;
use App\Models\User;

class PublicExamLinkPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isTeacher();
    }

    public function view(User $user, PublicExamLink $publicExamLink): bool
    {
        return $user->isTeacher();
    }

    public function create(User $user): bool
    {
        return $user->isTeacher();
    }

    public function update(User $user, PublicExamLink $publicExamLink): bool
    {
        return $user->isTeacher();
    }

    public function delete(User $user, PublicExamLink $publicExamLink): bool
    {
        return $user->isTeacher();
    }
}

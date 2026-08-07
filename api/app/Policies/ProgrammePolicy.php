<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\ContentStatus;
use App\Models\Programme;
use App\Models\User;

final class ProgrammePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Programme $programme): bool
    {
        return $user->isAdmin() || $programme->status === ContentStatus::Active;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Programme $programme): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Programme $programme): bool
    {
        return $user->isAdmin() && $programme->enrollments()->doesntExist();
    }

    public function enroll(User $user, Programme $programme): bool
    {
        return $user->isStudent()
            && $programme->status === ContentStatus::Active
            && $user->isActive();
    }
}

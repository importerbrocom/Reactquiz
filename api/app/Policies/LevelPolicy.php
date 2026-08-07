<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\ContentStatus;
use App\Models\Level;
use App\Models\User;

final class LevelPolicy
{
    public function viewAny(User $user): bool
    {
        return true;   // students browse the catalogue during onboarding
    }

    public function view(User $user, Level $level): bool
    {
        return $user->isAdmin() || $level->status === ContentStatus::Active;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Level $level): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Level $level): bool
    {
        return $user->isAdmin();
    }

    /**
     * A level cannot go live until it holds exactly the questions every student
     * must complete, and has a day container for each day.
     */
    public function publish(User $user, Level $level): bool
    {
        return $user->isAdmin() && $level->isPublishable();
    }
}

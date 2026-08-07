<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\ContentStatus;
use App\Models\ExamCategory;
use App\Models\User;

final class ExamCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ExamCategory $category): bool
    {
        return $user->isAdmin() || $category->status === ContentStatus::Active;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, ExamCategory $category): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, ExamCategory $category): bool
    {
        return $user->isAdmin() && $category->programmes()->doesntExist();
    }
}

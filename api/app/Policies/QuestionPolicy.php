<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Question;
use App\Models\User;

/**
 * Questions are admin-only content. Students never reach a Question through a
 * policy — they receive answer-free payloads assembled by the quiz services.
 */
final class QuestionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, Question $question): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Question $question): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Question $question): bool
    {
        return $user->isAdmin();
    }

    public function restore(User $user, Question $question): bool
    {
        return $user->isAdmin();
    }

    public function bulkUpdate(User $user): bool
    {
        return $user->isAdmin();
    }

    public function import(User $user): bool
    {
        return $user->isAdmin();
    }
}

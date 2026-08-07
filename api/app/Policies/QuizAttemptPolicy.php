<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\QuizAttempt;
use App\Models\User;

/**
 * Ownership gate for quiz attempts.
 *
 * Phase 3 adds the unlock check (QuizUnlockService) on top of this; ownership is
 * the invariant that belongs here, and it is what makes IDOR impossible: a
 * non-owned attempt fails this policy and is reported as 404, not 403, so ids
 * cannot be probed for existence.
 */
final class QuizAttemptPolicy
{
    public function view(User $user, QuizAttempt $attempt): bool
    {
        return $this->owns($user, $attempt);
    }

    public function submitAnswer(User $user, QuizAttempt $attempt): bool
    {
        return $this->owns($user, $attempt) && $attempt->isInProgress();
    }

    public function complete(User $user, QuizAttempt $attempt): bool
    {
        return $this->owns($user, $attempt);
    }

    private function owns(User $user, QuizAttempt $attempt): bool
    {
        return $user->getKey() === $attempt->user_id;
    }
}

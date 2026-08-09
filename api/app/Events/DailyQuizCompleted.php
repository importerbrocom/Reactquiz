<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\QuizAttempt;
use Illuminate\Foundation\Events\Dispatchable;

/** Fired after the completion transaction commits, never inside it. */
final class DailyQuizCompleted
{
    use Dispatchable;

    public function __construct(
        public readonly QuizAttempt $attempt,
        public readonly bool $levelTestUnlocked,
    ) {}
}

<?php

declare(strict_types=1);

namespace App\Actions\LevelTest;

use App\Enums\TestAttemptStatus;
use App\Events\LevelTestSubmitted;
use App\Models\LevelTestAttempt;
use Illuminate\Support\Facades\DB;

/**
 * Closes a month-end test and grades it.
 *
 * Grading runs INLINE rather than on the queue, deliberately. It costs a fixed four
 * statements no matter how many questions the test has (see GradeLevelTestAction), so
 * there is nothing to offload — while queueing it would make every result depend on a
 * worker being alive, and hand the student a spinner instead of their score. Genuinely
 * heavy follow-up work (certificate rendering, e-mail) belongs on the queue and hangs
 * off the LevelTestGraded event instead.
 */
final readonly class SubmitLevelTestAction
{
    public function __construct(
        private GradeLevelTestAction $grade,
    ) {}

    public function __invoke(LevelTestAttempt $attempt, bool $dueToExpiry = false): LevelTestAttempt
    {
        $closed = DB::transaction(function () use ($attempt, $dueToExpiry): ?LevelTestAttempt {
            /** @var LevelTestAttempt $locked */
            $locked = LevelTestAttempt::query()
                ->whereKey($attempt->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            // Already closed: a double-tap on Submit, or a retried offline request.
            if (! $locked->isOpen()) {
                return null;
            }

            $locked->forceFill([
                'status' => $dueToExpiry ? TestAttemptStatus::Expired : TestAttemptStatus::Submitted,
                'submitted_at' => now(),
                'time_spent_seconds' => $this->elapsedSeconds($locked),
            ])->save();

            return $locked;
        });

        if ($closed === null) {
            return $attempt->refresh();
        }

        LevelTestSubmitted::dispatch($closed, $dueToExpiry);

        // An expired attempt is still graded on whatever was answered. Discarding the
        // work would be a punishment, and there is no negative marking in this product.
        return ($this->grade)($closed);
    }

    /**
     * Elapsed time, derived from server timestamps and capped at the allowance.
     *
     * The client's own timer is cosmetic. A student who closes the tab for two days and
     * returns must not be recorded as having studied for two days, and a tampered
     * payload must not be able to claim a suspiciously fast finish either.
     */
    private function elapsedSeconds(LevelTestAttempt $attempt): int
    {
        $start = $attempt->started_at ?? $attempt->created_at ?? now();
        // Carbon 3 returns a float here.
        $elapsed = max(0, (int) now()->diffInSeconds($start, absolute: true));

        if ($attempt->time_limit_seconds === null) {
            return min($elapsed, 12 * 3600);
        }

        return min($elapsed, $attempt->time_limit_seconds + (int) config('quiz.test.grace_seconds'));
    }
}

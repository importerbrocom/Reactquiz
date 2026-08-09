<?php

declare(strict_types=1);

namespace App\Actions\LevelTest;

use App\Enums\TestAttemptStatus;
use App\Events\LevelTestStarted;
use App\Models\LevelEnrollment;
use App\Models\LevelTest;
use App\Models\LevelTestAttempt;
use App\Services\Quiz\QuizUnlockService;
use App\Support\SeededShuffler;
use Illuminate\Support\Facades\DB;

/**
 * Opens the month-end test.
 *
 * The test is made of exactly the questions the student practised over the 30 days —
 * their own 300, not a fresh sample — because the whole point is to check retention of
 * what they studied. What changes is the ORDER: a new `shuffle_seed` per attempt means
 * the same 300 questions arrive in a different sequence every time, and the answer
 * options are re-ordered per exposure on top of that (docs/adr/003). Recognising
 * "question 7 was the one about mitral stenosis" is worth nothing.
 *
 * `question_order` is PERSISTED rather than recomputed. It is the contract for the
 * whole attempt: pagination, resume-after-crash, and the review screen all index into
 * it, and none of them may disagree about what question 143 was.
 */
final readonly class StartLevelTestAction
{
    public function __construct(
        private QuizUnlockService $unlock,
        private SubmitLevelTestAction $submit,
    ) {}

    public function __invoke(LevelEnrollment $enrolment): LevelTestAttempt
    {
        // An abandoned attempt whose clock ran out is closed and graded before anything
        // else, otherwise it would block the retake the student is asking for.
        $this->closeExpiredAttempt($enrolment);

        // Re-checked here even though middleware also checks: this action is reachable
        // from an admin tool and from tests, and eligibility is not optional.
        $this->unlock->assertLevelTestUnlocked($enrolment);

        [$attempt, $isNew] = DB::transaction(function () use ($enrolment): array {
            /** @var LevelEnrollment $locked */
            $locked = LevelEnrollment::query()
                ->whereKey($enrolment->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            // Resume rather than duplicate. Two devices opening the test get the same
            // attempt, and therefore the same question order.
            $open = $locked->testAttempts()
                ->where('cycle_number', $locked->cycle_number)
                ->open()
                ->latest('id')
                ->first();

            if ($open !== null) {
                return [$open, false];
            }

            return [$this->create($locked), true];
        });

        if ($isNew) {
            LevelTestStarted::dispatch($attempt);
        }

        return $attempt;
    }

    private function create(LevelEnrollment $enrolment): LevelTestAttempt
    {
        /** @var LevelTest $test */
        $test = $enrolment->level->test;

        $attemptNumber = $enrolment->testAttempts()
            ->where('cycle_number', $enrolment->cycle_number)
            ->max('attempt_number');
        $attemptNumber = (int) $attemptNumber + 1;

        // Seeded from the attempt identity, so the order is reproducible for support
        // ("what did question 12 look like for this student?") yet different each retake.
        $seed = SeededShuffler::seedFrom(
            'test',
            $enrolment->getKey(),
            $enrolment->cycle_number,
            $attemptNumber,
            $enrolment->assignment_seed,
        );

        $order = $this->questionOrder($enrolment, $test, $seed);
        $timeLimit = $test->time_limit_minutes !== null
            ? $test->time_limit_minutes * 60
            : null;

        $attempt = new LevelTestAttempt;
        $attempt->forceFill([
            'user_id' => $enrolment->user_id,
            'level_test_id' => $test->getKey(),
            'level_enrollment_id' => $enrolment->getKey(),
            'level_id' => $enrolment->level_id,
            'cycle_number' => $enrolment->cycle_number,
            'attempt_number' => $attemptNumber,
            'status' => TestAttemptStatus::InProgress,
            'question_order' => $order,
            'shuffle_seed' => $seed,
            'total_questions' => count($order),
            'current_position' => 1,
            'time_limit_seconds' => $timeLimit,
            // Server-computed deadline. The client timer is display only.
            'expires_at' => $timeLimit === null ? null : now()->addSeconds($timeLimit),
            'started_at' => now(),
        ])->save();

        // Reloaded so DB defaults (answered_count, flagged_count) come back as 0 rather
        // than null, which would otherwise surface in the first window payload.
        return $attempt->refresh();
    }

    /**
     * The student's own 300 questions, shuffled for this attempt.
     *
     * Read from `enrollment_day_questions`, which is the record of what this student
     * actually practised. Sampling the level's pool instead would let a question they
     * never saw appear in their test.
     *
     * @return array<int, int>
     */
    private function questionOrder(LevelEnrollment $enrolment, LevelTest $test, int $seed): array
    {
        $questionIds = $enrolment->dayQuestions()
            ->orderBy('day_number')
            ->orderBy('position')
            ->pluck('question_id')
            ->map(static fn (int|string $id): int => (int) $id)
            ->all();

        if (! $test->shuffle_questions) {
            return $questionIds;
        }

        return SeededShuffler::shuffle($questionIds, $seed);
    }

    /**
     * Close out an attempt whose deadline has passed.
     *
     * Done on the way in rather than by a background sweep alone, so the student is
     * never told "you already have a test in progress" about an attempt that expired
     * while their laptop was shut.
     */
    private function closeExpiredAttempt(LevelEnrollment $enrolment): void
    {
        $open = $enrolment->testAttempts()
            ->where('cycle_number', $enrolment->cycle_number)
            ->open()
            ->latest('id')
            ->first();

        if ($open !== null && $open->hasExpired()) {
            ($this->submit)($open, dueToExpiry: true);
        }
    }
}

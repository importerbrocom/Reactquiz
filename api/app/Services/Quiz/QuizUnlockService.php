<?php

declare(strict_types=1);

namespace App\Services\Quiz;

use App\DTOs\Quiz\UnlockDecision;
use App\Enums\AttemptStatus;
use App\Enums\ContentStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\QuestionState;
use App\Enums\UnlockMode;
use App\Exceptions\LevelTestNotEligibleException;
use App\Exceptions\QuizDayLockedException;
use App\Models\LevelEnrollment;
use App\Models\QuizAttempt;
use Illuminate\Support\Collection;

/**
 * The only place that decides whether a student may open a quiz day or the month-end
 * test. Both the route middleware and the policies call in here, so no controller can
 * accidentally bypass it.
 *
 * Two rules govern the whole design:
 *
 *  1. A day counts as completed ONLY when an attempt exists with status=completed AND
 *     score = required_count (10/10). Nothing else counts — not a high score, not a
 *     "mostly done" attempt.
 *  2. `highest_unlocked_day` on the enrolment is a cache, never the authority.
 *     Access is always re-derived from completion records, so a corrupted counter
 *     cannot hand out a day the student has not earned.
 */
final class QuizUnlockService
{
    /**
     * Can this student open this day?
     *
     * @param  Collection<int, QuizAttempt>|null  $completions
     *                                                          Pre-loaded completion records. Supplied by dayStates() so building the
     *                                                          31-day timeline costs one query rather than one per day.
     */
    public function decideDay(
        LevelEnrollment $enrolment,
        int $day,
        ?Collection $completions = null,
    ): UnlockDecision {
        $level = $enrolment->level;

        // --- hard bounds ---------------------------------------------------- #
        if ($day === $level->test_day) {
            return $this->decideLevelTest($enrolment, $completions);
        }

        if ($day < 1 || $day > $level->total_quiz_days) {
            return UnlockDecision::deny(
                'day_out_of_range',
                "This level runs from day 1 to day {$level->total_quiz_days}.",
            );
        }

        // --- the enrolment must be usable ----------------------------------- #
        if ($enrolment->status !== EnrollmentStatus::Active) {
            return UnlockDecision::deny(
                'enrolment_inactive',
                'Your enrolment for this level is not active.',
            );
        }

        if ($level->status !== ContentStatus::Active) {
            return UnlockDecision::deny(
                'level_inactive',
                'This level is not currently available.',
            );
        }

        // --- day 1 is the entry point (business rule 3) --------------------- #
        if ($day === 1) {
            return UnlockDecision::allow();
        }

        // --- the previous day must be complete at full marks (rules 10, 11) - #
        $previous = $this->completionFor($enrolment, $day - 1, $completions);

        if ($previous === null) {
            $required = $level->daily_question_count;

            return UnlockDecision::deny(
                'previous_day_incomplete',
                "Complete Day {$day} − 1 with {$required}/{$required} to unlock Day {$day}.",
                blockingDay: $day - 1,
            );
        }

        // --- course-level pacing -------------------------------------------- #
        return match ($level->unlock_mode) {
            UnlockMode::Immediate => UnlockDecision::allow(),
            UnlockMode::NextCalendarDay => $this->decideNextCalendarDay($enrolment, $day, $previous),
            UnlockMode::Scheduled => $this->decideScheduled($enrolment, $day),
        };
    }

    public function assertDayUnlocked(LevelEnrollment $enrolment, int $day): void
    {
        $decision = $this->decideDay($enrolment, $day);

        if ($decision->denied()) {
            throw new QuizDayLockedException($decision->message ?? '', $decision->meta());
        }
    }

    /**
     * Month-end test eligibility: every day completed at full marks (rule 16).
     *
     * @param  Collection<int, QuizAttempt>|null  $completions
     */
    public function decideLevelTest(
        LevelEnrollment $enrolment,
        ?Collection $completions = null,
    ): UnlockDecision {
        $level = $enrolment->level;

        if ($enrolment->status !== EnrollmentStatus::Active) {
            return UnlockDecision::deny(
                'enrolment_inactive',
                'Your enrolment for this level is not active.',
            );
        }

        $completions ??= $this->loadCompletions($enrolment);
        $completedDays = $completions->keys()->all();
        $missing = array_values(array_diff(range(1, $level->total_quiz_days), $completedDays));

        if ($missing !== []) {
            $done = count($completedDays);

            return UnlockDecision::deny(
                'level_test_not_eligible',
                "Complete all {$level->total_quiz_days} daily quizzes with full marks first "
                    ."({$done} done).",
                blockingDay: $missing[0],
                context: [
                    'completed_days' => $done,
                    'required_days' => $level->total_quiz_days,
                    // Capped: a student on day 2 does not need a list of 28 numbers.
                    'missing_days' => array_slice($missing, 0, 10),
                ],
            );
        }

        $test = $enrolment->level->test;

        if ($test !== null && ! $test->allowsUnlimitedAttempts()) {
            $used = $enrolment->testAttempts()
                ->where('cycle_number', $enrolment->cycle_number)
                ->count();

            if ($used >= $test->attempt_limit) {
                return UnlockDecision::deny(
                    'attempt_limit_reached',
                    'You have used all available attempts for this test.',
                    context: ['attempts_used' => $used, 'attempts_allowed' => $test->attempt_limit],
                );
            }
        }

        return UnlockDecision::allow();
    }

    public function assertLevelTestUnlocked(LevelEnrollment $enrolment): void
    {
        $decision = $this->decideLevelTest($enrolment);

        if ($decision->denied()) {
            throw new LevelTestNotEligibleException($decision->message ?? '', $decision->meta());
        }
    }

    /**
     * State of every day in the level, for the timeline screen.
     *
     * One query for completions, one for in-progress attempts, then pure computation.
     * Never one query per day.
     *
     * @return array<int, array{day: int, status: string, score: int|null, required: int,
     *                          completed_at: string|null, unlock_hint: string|null,
     *                          unlocks_at: string|null}>
     */
    public function dayStates(LevelEnrollment $enrolment): array
    {
        $level = $enrolment->level;
        $completions = $this->loadCompletions($enrolment);

        $inProgress = QuizAttempt::query()
            ->where('level_enrollment_id', $enrolment->getKey())
            ->where('cycle_number', $enrolment->cycle_number)
            ->where('status', AttemptStatus::InProgress->value)
            ->get(['id', 'day_number', 'mastered_count', 'required_count'])
            ->keyBy('day_number');

        $states = [];

        for ($day = 1; $day <= $level->total_quiz_days; $day++) {
            $completed = $completions->get($day);
            $decision = $this->decideDay($enrolment, $day, $completions);

            $status = match (true) {
                $completed !== null => 'completed',
                $inProgress->has($day) => 'in_progress',
                $decision->allowed => 'available',
                default => 'locked',
            };

            $states[] = [
                'day' => $day,
                'status' => $status,
                'score' => $completed?->score ?? $inProgress->get($day)?->mastered_count,
                'required' => $level->daily_question_count,
                'completed_at' => $completed?->completed_at?->toIso8601String(),
                'unlock_hint' => $status === 'locked' ? $decision->message : null,
                'unlocks_at' => $decision->unlocksAt?->toIso8601String(),
            ];
        }

        return $states;
    }

    /** How many days of this level the student has completed at full marks. */
    public function completedDayCount(LevelEnrollment $enrolment): int
    {
        return $this->loadCompletions($enrolment)->count();
    }

    /**
     * Highest day the student may currently open — a pure function of completions,
     * used to refresh the cached counter on the enrolment.
     */
    public function highestUnlockedDay(LevelEnrollment $enrolment): int
    {
        $completions = $this->loadCompletions($enrolment);
        $highest = 1;

        for ($day = 2; $day <= $enrolment->level->total_quiz_days; $day++) {
            if ($this->completionFor($enrolment, $day - 1, $completions) === null) {
                break;
            }
            $highest = $day;
        }

        return $highest;
    }

    // ----------------------------------------------------------------- internals --

    /**
     * Completed-at-full-marks attempts for this enrolment's current cycle, keyed by day.
     *
     * @return Collection<int, QuizAttempt>
     */
    private function loadCompletions(LevelEnrollment $enrolment): Collection
    {
        return QuizAttempt::query()
            ->where('level_enrollment_id', $enrolment->getKey())
            ->where('cycle_number', $enrolment->cycle_number)
            ->completedFullScore()
            ->get(['id', 'day_number', 'score', 'required_count', 'completed_at'])
            // A day can only be completed once, but ordering makes the pick explicit.
            ->sortBy('completed_at')
            ->keyBy('day_number');
    }

    /** @param  Collection<int, QuizAttempt>|null  $completions */
    private function completionFor(
        LevelEnrollment $enrolment,
        int $day,
        ?Collection $completions,
    ): ?QuizAttempt {
        if ($completions !== null) {
            return $completions->get($day);
        }

        return QuizAttempt::query()
            ->where('level_enrollment_id', $enrolment->getKey())
            ->where('cycle_number', $enrolment->cycle_number)
            ->where('day_number', $day)
            ->completedFullScore()
            ->first(['id', 'day_number', 'score', 'required_count', 'completed_at']);
    }

    private function decideNextCalendarDay(
        LevelEnrollment $enrolment,
        int $day,
        QuizAttempt $previous,
    ): UnlockDecision {
        $timezone = $enrolment->user?->timezone ?? config('quiz.default_timezone');

        // Compared in the STUDENT's local dates, not the server's, or a student in
        // IST would see the next day appear at 05:30 local.
        $completedOn = $previous->completed_at?->timezone($timezone)->startOfDay();
        $today = now()->timezone($timezone)->startOfDay();

        if ($completedOn === null || $today->greaterThan($completedOn)) {
            return UnlockDecision::allow();
        }

        return UnlockDecision::deny(
            'available_tomorrow',
            'Well done — Day '.$day.' opens tomorrow.',
            unlocksAt: $completedOn->copy()->addDay(),
        );
    }

    private function decideScheduled(LevelEnrollment $enrolment, int $day): UnlockDecision
    {
        $timezone = $enrolment->user?->timezone ?? config('quiz.default_timezone');
        $start = ($enrolment->started_at ?? $enrolment->created_at)?->timezone($timezone)->startOfDay();

        if ($start === null) {
            return UnlockDecision::allow();
        }

        $availableOn = $start->copy()->addDays($day - 1);

        if (now()->timezone($timezone)->startOfDay()->greaterThanOrEqualTo($availableOn)) {
            return UnlockDecision::allow();
        }

        return UnlockDecision::deny(
            'scheduled_later',
            'Day '.$day.' opens on '.$availableOn->isoFormat('D MMM YYYY').'.',
            unlocksAt: $availableOn,
        );
    }

    /** Outstanding question ids in an attempt — used by the 422 on premature completion. */
    public function outstandingQuestionIds(QuizAttempt $attempt): array
    {
        return $attempt->questions()
            ->whereNot('state', QuestionState::Mastered->value)
            ->pluck('question_id')
            ->all();
    }
}

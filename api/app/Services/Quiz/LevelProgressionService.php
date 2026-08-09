<?php

declare(strict_types=1);

namespace App\Services\Quiz;

use App\DTOs\Quiz\ProgressionDecision;
use App\Enums\TestAttemptStatus;
use App\Enums\UnlockMode;
use App\Models\LevelEnrollment;
use App\Models\Programme;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Moving from one month to the next (docs/adr/002).
 *
 * Six levels make a cycle; finishing the sixth starts cycle 2 over the SAME 1,800
 * questions with fresh per-student seeds, which is what the client asked for. Cycles are
 * not a special case in the code: a cycle is just a new `level_enrollments` row with a
 * new `assignment_seed`, so the same dealing logic produces a completely different
 * 30-day grouping without a line of cycle-specific code.
 *
 * Note what advancing does NOT do: it does not close or lock the level just finished.
 * Retries are unlimited, so a student on level 3 must still be able to redo day 5 of
 * level 1. Progress lives on the programme enrolment; the level enrolments stay open.
 */
final class LevelProgressionService
{
    public function __construct(
        private readonly QuizUnlockService $unlock,
    ) {}

    public function decide(LevelEnrollment $enrolment): ProgressionDecision
    {
        $level = $enrolment->level;
        $programme = $enrolment->programmeEnrollment->programme;

        // --- the level itself must be finished ------------------------------- #
        $completedDays = $this->unlock->completedDayCount($enrolment);

        if ($completedDays < $level->total_quiz_days) {
            return ProgressionDecision::deny(
                'days_outstanding',
                "Finish all {$level->total_quiz_days} days of this level first.",
                ['completed_days' => $completedDays, 'required_days' => $level->total_quiz_days],
            );
        }

        // --- the month-end test must have been sat --------------------------- #
        // Not merely unlocked: the test IS the month's assessment, and skipping
        // straight past it would make the 30 days of practice unexamined.
        $graded = $enrolment->testAttempts()
            ->where('cycle_number', $enrolment->cycle_number)
            ->where('status', TestAttemptStatus::Graded->value)
            ->exists();

        if (! $graded) {
            return ProgressionDecision::deny(
                'test_not_taken',
                'Take the month-end test to complete this level.',
            );
        }

        // Passing is only required when the programme says so (default: it does not —
        // the test is there to measure retention, not to trap students on a level).
        if ($programme->require_test_pass_to_advance && $enrolment->test_passed_at === null) {
            $best = (float) ($enrolment->testAttempts()
                ->where('cycle_number', $enrolment->cycle_number)
                ->max('percentage') ?? 0);

            return ProgressionDecision::deny(
                'test_not_passed',
                'Reach the pass mark in the month-end test to move on. Attempts are unlimited.',
                [
                    'best_percentage' => $best,
                    'pass_percentage' => (float) ($level->test?->pass_percentage ?? $level->pass_percentage ?? 50),
                ],
            );
        }

        return $this->target($enrolment, $programme, $level->level_number);
    }

    /**
     * When the level was actually finished.
     *
     * This has to be anchored to the completion, not to `now()`: a "next calendar day"
     * unlock computed from the current time would recede by a day every time it was
     * asked, and the next level would never open at all.
     */
    private function finishedAt(LevelEnrollment $enrolment): CarbonInterface
    {
        $testGradedAt = $enrolment->testAttempts()
            ->where('cycle_number', $enrolment->cycle_number)
            ->where('status', TestAttemptStatus::Graded->value)
            ->max('graded_at');

        $candidates = array_filter([
            $testGradedAt === null ? null : Carbon::parse($testGradedAt),
            $enrolment->last_completed_at,
        ]);

        return $candidates === [] ? now() : max($candidates);
    }

    /** Where the student goes next: next level, next cycle, or out of the programme. */
    private function target(
        LevelEnrollment $enrolment,
        Programme $programme,
        int $currentLevelNumber,
    ): ProgressionDecision {
        // Midnight in the STUDENT's zone, one day after they finished. Server dates
        // would open the next level at 05:30 local for an Indian student.
        $unlocksAt = $programme->level_unlock_mode === UnlockMode::NextCalendarDay
            ? $this->finishedAt($enrolment)
                ->copy()
                ->timezone($enrolment->user?->timezone ?? config('quiz.default_timezone'))
                ->addDay()
                ->startOfDay()
            : null;

        if ($currentLevelNumber < $programme->total_levels) {
            return ProgressionDecision::advance(
                targetLevelNumber: $currentLevelNumber + 1,
                targetCycle: $enrolment->cycle_number,
                unlocksAt: $unlocksAt,
            );
        }

        // Last level of the cycle. total_cycles = 0 means repeat indefinitely.
        $moreCycles = $programme->total_cycles === 0
            || $enrolment->cycle_number < $programme->total_cycles;

        if ($moreCycles) {
            return ProgressionDecision::advance(
                targetLevelNumber: 1,
                targetCycle: $enrolment->cycle_number + 1,
                unlocksAt: $unlocksAt,
            );
        }

        return ProgressionDecision::finished($programme->next_programme_id);
    }
}

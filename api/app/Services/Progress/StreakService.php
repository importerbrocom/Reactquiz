<?php

declare(strict_types=1);

namespace App\Services\Progress;

use App\Models\LevelEnrollment;
use App\Models\StudentStreak;

/**
 * Daily streaks, counted in the STUDENT's local dates.
 *
 * Using server dates would roll a student's streak over at 05:30 local time in IST,
 * breaking it for anyone studying late in the evening.
 */
final class StreakService
{
    public function registerCompletion(LevelEnrollment $enrolment): StudentStreak
    {
        $user = $enrolment->user;
        $timezone = $user?->timezone ?? config('quiz.default_timezone');
        $today = now()->timezone($timezone)->startOfDay();

        /** @var StudentStreak $streak */
        $streak = StudentStreak::query()->firstOrNew([
            'user_id' => $enrolment->user_id,
            'programme_id' => $enrolment->programmeEnrollment->programme_id,
        ]);

        // Compared as CALENDAR DATES, never as instants. `last_activity_date` is a
        // date column, so it reads back as midnight UTC, whereas `$today` is midnight
        // in the student's own zone. Comparing the two as timestamps would differ by
        // the UTC offset and never match — silently resetting every IST student's
        // streak to 1 each day.
        $last = $streak->last_activity_date?->toDateString();
        $todayDate = $today->toDateString();
        $yesterdayDate = $today->copy()->subDay()->toDateString();

        $current = match ($last) {
            // Already counted today: finishing two days in one sitting is one day of
            // streak, not two. Otherwise a binge would inflate it.
            $todayDate => max(1, $streak->current_streak),
            $yesterdayDate => $streak->current_streak + 1,
            default => 1,
        };

        $streak->forceFill([
            'current_streak' => $current,
            'longest_streak' => max($streak->longest_streak, $current),
            'last_activity_date' => $today->toDateString(),
        ])->save();

        return $streak;
    }
}

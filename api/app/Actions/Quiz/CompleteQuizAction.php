<?php

declare(strict_types=1);

namespace App\Actions\Quiz;

use App\DTOs\Quiz\CompletionResultData;
use App\Enums\AttemptStatus;
use App\Enums\QuestionState;
use App\Events\DailyQuizCompleted;
use App\Events\LevelTestUnlocked;
use App\Exceptions\QuizNotCompleteException;
use App\Models\LevelEnrollment;
use App\Models\QuizAttempt;
use App\Services\Progress\StreakService;
use App\Services\Quiz\QuizUnlockService;
use Illuminate\Support\Facades\DB;

/**
 * Marks a day complete — but only if it genuinely is.
 *
 * Business rules 8, 9, 10 and 23. The score is RE-DERIVED from the per-question state
 * table inside the transaction; neither the cached counter nor anything from the
 * client is trusted. A client that posts "score: 10" gets nothing, because no such
 * field is read anywhere.
 *
 * Concurrency: two simultaneous calls both try to lock the attempt row. MySQL
 * serialises them; the first commits with status=completed, the second then re-reads
 * inside its own transaction, sees `completed`, and returns the existing result. So no
 * double streak increment, no double unlock, no duplicate certificate.
 */
final readonly class CompleteQuizAction
{
    public function __construct(
        private QuizUnlockService $unlock,
        private StreakService $streaks,
    ) {}

    public function __invoke(QuizAttempt $attempt): CompletionResultData
    {
        [$result, $justCompleted] = DB::transaction(function () use ($attempt): array {
            /** @var QuizAttempt $locked */
            $locked = QuizAttempt::query()
                ->whereKey($attempt->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            // Idempotent by design: a retried request is a success, not a conflict.
            if ($locked->status === AttemptStatus::Completed) {
                return [$this->buildResult($locked, alreadyCompleted: true), false];
            }

            // The authoritative 10/10 check. Counted, not trusted.
            $mastered = $locked->questions()
                ->where('state', QuestionState::Mastered->value)
                ->count();

            if ($mastered < $locked->required_count) {
                throw new QuizNotCompleteException(
                    "Answer all {$locked->required_count} questions correctly to complete this day.",
                    [
                        'mastered_count' => $mastered,
                        'required_count' => $locked->required_count,
                        'outstanding_question_ids' => $this->unlock->outstandingQuestionIds($locked),
                    ],
                );
            }

            $locked->forceFill([
                'status' => AttemptStatus::Completed,
                'score' => $mastered,
                'mastered_count' => $mastered,
                'completed_at' => now(),
                'last_activity_at' => now(),
            ])->save();

            $this->refreshEnrolment($locked);
            $this->streaks->registerCompletion($locked->levelEnrollment->refresh());

            return [$this->buildResult($locked->refresh()), true];
        });

        // Events fire after commit so a listener can never observe a half-written state.
        if ($justCompleted) {
            DailyQuizCompleted::dispatch($attempt->refresh(), $result->levelTestUnlocked);

            if ($result->levelTestUnlocked) {
                LevelTestUnlocked::dispatch($attempt->levelEnrollment->refresh());
            }
        }

        return $result;
    }

    /**
     * Recompute the enrolment's cached progress from completion records.
     *
     * These columns exist so dashboards can be read cheaply; they are always derived,
     * never incremented blindly, so they cannot drift out of agreement with the
     * attempts that back them.
     */
    private function refreshEnrolment(QuizAttempt $attempt): void
    {
        /** @var LevelEnrollment $enrolment */
        $enrolment = LevelEnrollment::query()
            ->whereKey($attempt->level_enrollment_id)
            ->lockForUpdate()
            ->firstOrFail();

        $level = $enrolment->level;
        $completedDays = $this->unlock->completedDayCount($enrolment);
        $allDaysDone = $completedDays >= $level->total_quiz_days;

        $enrolment->forceFill([
            'completed_days' => $completedDays,
            'last_completed_day' => $attempt->day_number,
            'last_completed_at' => now(),
            'current_day' => min($attempt->day_number + 1, $level->test_day),
            'highest_unlocked_day' => $this->unlock->highestUnlockedDay($enrolment),
            'progress_percent' => round($completedDays / max(1, $level->total_quiz_days) * 100, 2),
            'total_study_seconds' => $enrolment->total_study_seconds + $attempt->time_spent_seconds,
            // Stamped once, on the completion that finishes the level.
            'test_unlocked_at' => $allDaysDone ? ($enrolment->test_unlocked_at ?? now()) : null,
        ])->save();
    }

    private function buildResult(QuizAttempt $attempt, bool $alreadyCompleted = false): CompletionResultData
    {
        $enrolment = $attempt->levelEnrollment->refresh();
        $level = $enrolment->level;

        $nextDay = $attempt->day_number + 1;
        $isTestNext = $nextDay > $level->total_quiz_days;

        $nextDecision = $isTestNext
            ? $this->unlock->decideLevelTest($enrolment)
            : $this->unlock->decideDay($enrolment, $nextDay);

        $streak = $enrolment->user
            ?->streaks()
            ->where('programme_id', $enrolment->programmeEnrollment->programme_id)
            ->first();

        return new CompletionResultData(
            dayNumber: $attempt->day_number,
            score: $attempt->score,
            requiredCount: $attempt->required_count,
            completedDays: $enrolment->completed_days,
            totalDays: $level->total_quiz_days,
            timeSpentSeconds: $attempt->time_spent_seconds,
            currentStreak: $streak?->current_streak ?? 0,
            longestStreak: $streak?->longest_streak ?? 0,
            nextDay: $isTestNext ? null : $nextDay,
            nextDayUnlocked: ! $isTestNext && $nextDecision->allowed,
            nextDayUnlocksAt: $nextDecision->unlocksAt?->toIso8601String(),
            nextDayHint: $isTestNext ? null : ($nextDecision->allowed ? null : $nextDecision->message),
            levelTestUnlocked: $enrolment->test_unlocked_at !== null,
            alreadyCompleted: $alreadyCompleted,
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Actions\Quiz;

use App\Enums\EnrollmentStatus;
use App\Events\LevelAdvanced;
use App\Exceptions\LevelAdvanceNotAllowedException;
use App\Models\Level;
use App\Models\LevelEnrollment;
use App\Models\ProgrammeEnrollment;
use App\Services\Quiz\LevelProgressionService;
use Illuminate\Support\Facades\DB;

/**
 * Moves a student on to the next month.
 *
 * Idempotent: if the target enrolment already exists it is returned rather than
 * duplicated, so a double-tap on "Start level 2" cannot produce two enrolments with two
 * different question deals — which would be indistinguishable from data corruption from
 * the student's point of view.
 *
 * The level just finished is left open on purpose (see LevelProgressionService).
 */
final readonly class AdvanceLevelAction
{
    public function __construct(
        private LevelProgressionService $progression,
        private AssignLevelQuestionsAction $assign,
    ) {}

    public function __invoke(LevelEnrollment $current): LevelEnrollment
    {
        $decision = $this->progression->decide($current);

        if ($decision->denied()) {
            throw new LevelAdvanceNotAllowedException($decision->message ?? '', $decision->toArray());
        }

        if ($decision->unlocksAt !== null && $decision->unlocksAt->isFuture()) {
            throw new LevelAdvanceNotAllowedException(
                'Your next level opens tomorrow.',
                $decision->toArray(),
            );
        }

        [$next, $isNew] = DB::transaction(function () use ($current, $decision): array {
            /** @var ProgrammeEnrollment $programmeEnrolment */
            $programmeEnrolment = ProgrammeEnrollment::query()
                ->whereKey($current->programme_enrollment_id)
                ->lockForUpdate()
                ->firstOrFail();

            /** @var Level $targetLevel */
            $targetLevel = Level::query()
                ->where('programme_id', $programmeEnrolment->programme_id)
                ->where('level_number', $decision->targetLevelNumber)
                ->firstOrFail();

            $existing = LevelEnrollment::query()
                ->where('user_id', $current->user_id)
                ->where('level_id', $targetLevel->getKey())
                ->where('cycle_number', $decision->targetCycle)
                ->first();

            if ($existing !== null) {
                return [$existing, false];
            }

            $enrolment = new LevelEnrollment;
            $enrolment->forceFill([
                'programme_enrollment_id' => $programmeEnrolment->getKey(),
                'user_id' => $current->user_id,
                'level_id' => $targetLevel->getKey(),
                'cycle_number' => $decision->targetCycle,
                'status' => EnrollmentStatus::Active,
                'is_active' => true,
                // A fresh seed is the whole mechanism behind "cycle 2 feels new".
                'assignment_seed' => random_int(1, 2_000_000_000),
                'started_at' => now(),
            ])->save();

            $this->markCurrentComplete($current);
            $this->refreshProgrammeProgress($programmeEnrolment, $decision->targetLevelNumber, $decision->targetCycle);

            return [$enrolment->refresh(), true];
        });

        if ($isNew) {
            // Deal the next 300 questions up front rather than on first open, so the
            // dashboard can show day 1 immediately.
            ($this->assign)($next);
            LevelAdvanced::dispatch($current->refresh(), $next->refresh());
        }

        return $next->refresh();
    }

    /**
     * Stamp the finished level without locking it.
     *
     * `status` stays Active deliberately: unlimited retries means a student on level 3
     * must still be able to redo day 5 of level 1, and the unlock service refuses any
     * enrolment that is not Active.
     */
    private function markCurrentComplete(LevelEnrollment $current): void
    {
        if ($current->completed_at !== null) {
            return;
        }

        $current->forceFill(['completed_at' => now()])->save();
    }

    private function refreshProgrammeProgress(
        ProgrammeEnrollment $programmeEnrolment,
        int $targetLevelNumber,
        int $targetCycle,
    ): void {
        $newCycle = $targetCycle > $programmeEnrolment->current_cycle;

        // Derived by counting completions, not by incrementing a counter that could
        // drift if this ever ran twice.
        $completedThisCycle = LevelEnrollment::query()
            ->where('programme_enrollment_id', $programmeEnrolment->getKey())
            ->where('cycle_number', $programmeEnrolment->current_cycle)
            ->whereNotNull('completed_at')
            ->count();

        $totalCompleted = LevelEnrollment::query()
            ->where('programme_enrollment_id', $programmeEnrolment->getKey())
            ->whereNotNull('completed_at')
            ->count();

        $totalLevels = max(1, $programmeEnrolment->programme->total_levels);

        $programmeEnrolment->forceFill([
            'current_level' => $targetLevelNumber,
            'current_cycle' => $targetCycle,
            'levels_completed_this_cycle' => $newCycle ? 0 : $completedThisCycle,
            'total_levels_completed' => $totalCompleted,
            'overall_progress_percent' => round(
                min($completedThisCycle, $totalLevels) / $totalLevels * 100,
                2,
            ),
            'started_at' => $programmeEnrolment->started_at ?? now(),
        ])->save();
    }
}

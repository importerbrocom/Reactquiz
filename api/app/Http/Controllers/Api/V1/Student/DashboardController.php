<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Student;

use App\DTOs\Quiz\UnlockDecision;
use App\Enums\AttemptStatus;
use App\Http\Controllers\Controller;
use App\Models\LevelEnrollment;
use App\Models\QuizAttempt;
use App\Services\Quiz\LevelProgressionService;
use App\Services\Quiz\QuizUnlockService;
use App\Services\Student\StudentContext;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The home screen: where am I, what do I do next, and how am I doing.
 *
 * Deliberately assembled from the cached counters on the enrolment rather than by
 * re-deriving progress. This is the most-requested endpoint in the product — it is hit
 * on every app open — so it must not run an aggregate over the attempt history. The
 * counters are refreshed on completion, which is far rarer than reading them.
 */
final class DashboardController extends Controller
{
    public function __construct(
        private readonly StudentContext $context,
        private readonly QuizUnlockService $unlock,
        private readonly LevelProgressionService $progression,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $programmeEnrolment = $this->context->programmeEnrolment($user);
        $enrolment = $this->context->currentLevelEnrolment($user);
        $level = $enrolment->level;
        $programme = $programmeEnrolment->programme;

        $streak = $user->streaks()
            ->where('programme_id', $programme->getKey())
            ->first();

        $inProgress = QuizAttempt::query()
            ->where('level_enrollment_id', $enrolment->getKey())
            ->where('status', AttemptStatus::InProgress->value)
            ->latest('last_activity_at')
            ->first();

        $nextDay = min($enrolment->current_day, $level->total_quiz_days);
        $nextDecision = $this->unlock->decideDay($enrolment, $nextDay);

        return ApiResponse::success([
            'student' => [
                'name' => $user->name,
                'timezone' => $user->timezone,
            ],
            'programme' => [
                'title' => $programme->title,
                'exam' => $programme->examCategory?->title,
                'current_level' => $programmeEnrolment->current_level,
                'total_levels' => $programme->total_levels,
                'current_cycle' => $programmeEnrolment->current_cycle,
                'total_cycles' => $programme->total_cycles,
                'overall_percent' => (float) $programmeEnrolment->overall_progress_percent,
            ],
            'level' => [
                'number' => $level->level_number,
                'title' => $level->title,
                'completed_days' => $enrolment->completed_days,
                'total_days' => $level->total_quiz_days,
                'percent' => (float) $enrolment->progress_percent,
                'questions_per_day' => $level->daily_question_count,
            ],
            // The single call to action. The client should need no logic of its own to
            // decide what the big button says.
            'next_action' => $this->nextAction($enrolment, $inProgress, $nextDay, $nextDecision),
            'streak' => [
                'current' => $streak?->current_streak ?? 0,
                'longest' => $streak?->longest_streak ?? 0,
                'last_activity_date' => $streak?->last_activity_date?->toDateString(),
            ],
            'study_time_seconds' => $enrolment->total_study_seconds,
            'level_test' => $this->unlock->decideLevelTest($enrolment)->toArray(),
        ]);
    }

    /** @return array<string, mixed> */
    private function nextAction(
        LevelEnrollment $enrolment,
        ?QuizAttempt $inProgress,
        int $nextDay,
        UnlockDecision $decision,
    ): array {
        if ($inProgress !== null) {
            return [
                'type' => 'resume_day',
                'day' => $inProgress->day_number,
                'attempt_uuid' => $inProgress->uuid,
                'mastered_count' => $inProgress->mastered_count,
                'required_count' => $inProgress->required_count,
                'label' => "Resume day {$inProgress->day_number}",
            ];
        }

        if ($enrolment->hasCompletedAllDays()) {
            $advance = $this->progression->decide($enrolment);

            // Every day done but the test not yet sat: the test is the next thing.
            if ($advance->denied() && $advance->reason === 'test_not_taken') {
                return ['type' => 'take_level_test', 'label' => 'Take the month-end test'];
            }

            if ($advance->allowed) {
                return [
                    'type' => 'advance_level',
                    'next_level' => $advance->targetLevelNumber,
                    'next_cycle' => $advance->targetCycle,
                    'unlocks_at' => $advance->unlocksAt?->toIso8601String(),
                    'label' => 'Start level '.$advance->targetLevelNumber,
                ];
            }

            if ($advance->programmeCompleted) {
                return [
                    'type' => 'programme_complete',
                    'label' => 'You have finished this programme',
                    'next_programme_id' => $advance->nextProgrammeId,
                ];
            }

            // Left over: a pass is required and has not been reached. Attempts are
            // unlimited, so this is a nudge rather than a dead end.
            return [
                'type' => 'retake_level_test',
                'label' => 'Retake the month-end test',
                'hint' => $advance->message,
            ];
        }

        if ($decision->denied()) {
            return [
                'type' => 'locked',
                'day' => $nextDay,
                'label' => $decision->message,
                'unlocks_at' => $decision->unlocksAt?->toIso8601String(),
            ];
        }

        return [
            'type' => 'start_day',
            'day' => $nextDay,
            'label' => "Start day {$nextDay}",
        ];
    }
}

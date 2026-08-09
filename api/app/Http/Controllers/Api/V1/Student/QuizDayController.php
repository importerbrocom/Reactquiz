<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Controller;
use App\Services\Quiz\QuizUnlockService;
use App\Services\Student\StudentContext;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The month timeline, and the state of a single day.
 */
final class QuizDayController extends Controller
{
    public function __construct(
        private readonly StudentContext $context,
        private readonly QuizUnlockService $unlock,
    ) {}

    /**
     * The 30-day timeline.
     *
     * Two queries for the whole month, not one per day — see QuizUnlockService::dayStates.
     */
    public function index(Request $request): JsonResponse
    {
        $enrolment = $this->context->currentLevelEnrolment($request->user());
        $level = $enrolment->level;

        return ApiResponse::success([
            'level' => [
                'number' => $level->level_number,
                'title' => $level->title,
                'cycle' => $enrolment->cycle_number,
                'total_days' => $level->total_quiz_days,
                'questions_per_day' => $level->daily_question_count,
                'test_day' => $level->test_day,
            ],
            'progress' => [
                'completed_days' => $enrolment->completed_days,
                'current_day' => $enrolment->current_day,
                'percent' => (float) $enrolment->progress_percent,
            ],
            'days' => $this->unlock->dayStates($enrolment),
            'level_test' => $this->unlock->decideLevelTest($enrolment)->toArray(),
        ]);
    }

    /**
     * Whether a given day may be opened, and why not if it may not.
     *
     * A separate, cheap endpoint from starting the day, so the client can render a
     * locked state without creating an attempt as a side effect of looking.
     */
    public function show(Request $request, int $day): JsonResponse
    {
        $enrolment = $this->context->currentLevelEnrolment($request->user());
        $decision = $this->unlock->decideDay($enrolment, $day);

        if ($decision->denied()) {
            // 423 Locked, not 403: the student is not forbidden, the day is not yet open.
            return ApiResponse::error(
                $decision->message ?? 'This day is locked.',
                423,
                'QUIZ_DAY_LOCKED',
                meta: $decision->meta(),
            );
        }

        return ApiResponse::success([
            'day' => $day,
            'unlocked' => true,
            'question_count' => $enrolment->level->daily_question_count,
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Student;

use App\Actions\Quiz\CompleteQuizAction;
use App\Actions\Quiz\StartQuizAttemptAction;
use App\Http\Controllers\Controller;
use App\Services\Quiz\DailyQuizDeliveryService;
use App\Services\Student\StudentContext;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Starting, resuming, reading and completing one day's quiz.
 */
final class QuizAttemptController extends Controller
{
    public function __construct(
        private readonly StudentContext $context,
        private readonly StartQuizAttemptAction $startAttempt,
        private readonly CompleteQuizAction $complete,
        private readonly DailyQuizDeliveryService $delivery,
    ) {}

    /**
     * Open the day.
     *
     * POST rather than GET because it may create an attempt — but it is safe to repeat:
     * a second call resumes the same attempt rather than starting a new one, so a
     * double-tap or a retried request cannot fork a student's progress.
     */
    public function store(Request $request, int $day): JsonResponse
    {
        $enrolment = $this->context->currentLevelEnrolment($request->user());
        $attempt = ($this->startAttempt)($enrolment, $day);

        return ApiResponse::success(
            $this->delivery->payload($attempt),
            'Quiz ready.',
        );
    }

    /** Re-read an attempt, for resuming after a reload. */
    public function show(Request $request, string $attempt): JsonResponse
    {
        $model = $this->context->quizAttempt($request->user(), $attempt);

        return ApiResponse::success($this->delivery->payload($model));
    }

    /**
     * Claim the day as finished.
     *
     * The server re-counts mastered questions from its own records; nothing in the
     * request body is read. A replay returns the original result rather than an error.
     */
    public function complete(Request $request, string $attempt): JsonResponse
    {
        $model = $this->context->quizAttempt($request->user(), $attempt);
        $result = ($this->complete)($model);

        return ApiResponse::sensitive(
            $result->toArray(),
            $result->alreadyCompleted ? 'Day already completed.' : 'Day completed.',
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Student;

use App\Actions\LevelTest\StartLevelTestAction;
use App\Actions\LevelTest\SubmitLevelTestAction;
use App\DTOs\Quiz\TestResultData;
use App\Enums\TestAttemptStatus;
use App\Http\Controllers\Controller;
use App\Models\LevelTestAttempt;
use App\Services\Quiz\LevelTestDeliveryService;
use App\Services\Quiz\QuizUnlockService;
use App\Services\Student\StudentContext;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The month-end test.
 */
final class LevelTestController extends Controller
{
    public function __construct(
        private readonly StudentContext $context,
        private readonly QuizUnlockService $unlock,
        private readonly StartLevelTestAction $startTest,
        private readonly SubmitLevelTestAction $submitTest,
        private readonly LevelTestDeliveryService $delivery,
    ) {}

    /** Eligibility, past attempts, and any test still in progress. */
    public function show(Request $request): JsonResponse
    {
        $enrolment = $this->context->currentLevelEnrolment($request->user());
        $test = $enrolment->level->test;

        $attempts = $enrolment->testAttempts()
            ->where('cycle_number', $enrolment->cycle_number)
            ->orderBy('attempt_number')
            ->get();

        return ApiResponse::success([
            'eligibility' => $this->unlock->decideLevelTest($enrolment)->toArray(),
            'test' => $test === null ? null : [
                'question_count' => $enrolment->dayQuestions()->count(),
                'pass_percentage' => (float) $test->pass_percentage,
                'time_limit_minutes' => $test->time_limit_minutes,
                'attempt_limit' => $test->attempt_limit,
                'unlimited_attempts' => $test->allowsUnlimitedAttempts(),
            ],
            'open_attempt' => $attempts->first(fn (LevelTestAttempt $a): bool => $a->isOpen())?->uuid,
            'attempts' => $attempts->map(fn (LevelTestAttempt $a): array => [
                'uuid' => $a->uuid,
                'attempt_number' => $a->attempt_number,
                'status' => $a->status->value,
                'submitted_at' => $a->submitted_at?->toIso8601String(),
                // Withheld until released, exactly as in the result endpoint.
                'percentage' => $a->resultsAreReleased() ? (float) $a->percentage : null,
                'passed' => $a->resultsAreReleased() ? $a->passed : null,
            ])->all(),
            'best_percentage' => $attempts
                ->filter(fn (LevelTestAttempt $a): bool => $a->resultsAreReleased())
                ->max(fn (LevelTestAttempt $a): float => (float) $a->percentage),
        ]);
    }

    /** Start, or resume, the test. Safe to repeat. */
    public function store(Request $request): JsonResponse
    {
        $enrolment = $this->context->currentLevelEnrolment($request->user());
        $attempt = ($this->startTest)($enrolment);

        return ApiResponse::success(
            $this->delivery->window($attempt, 1),
            'Test ready.',
        );
    }

    /**
     * A window of the paper.
     *
     * The whole point of windowing: a 300-question paper is 1–2 MB of JSON, which is a
     * long stall on a phone and hands the entire paper to devtools in one go.
     */
    public function questions(Request $request, string $attempt): JsonResponse
    {
        $validated = $request->validate([
            'position' => ['nullable', 'integer', 'min:1'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.config('quiz.test.window_size')],
        ]);

        $model = $this->context->testAttempt($request->user(), $attempt);

        return ApiResponse::success($this->delivery->window(
            $model,
            (int) ($validated['position'] ?? 1),
            isset($validated['limit']) ? (int) $validated['limit'] : null,
        ));
    }

    /** The answered/flagged grid, with nothing about correctness. */
    public function navigator(Request $request, string $attempt): JsonResponse
    {
        $model = $this->context->testAttempt($request->user(), $attempt);

        return ApiResponse::success([
            'total_questions' => $model->total_questions,
            'answered_count' => $model->answered_count,
            'flagged_count' => $model->flagged_count,
            'questions' => $this->delivery->navigator($model),
        ]);
    }

    /** Hand the paper in. Idempotent. */
    public function submit(Request $request, string $attempt): JsonResponse
    {
        $model = $this->context->testAttempt($request->user(), $attempt);
        $graded = ($this->submitTest)($model);

        return ApiResponse::sensitive($this->buildResult($graded)->toArray(), 'Test submitted.');
    }

    public function result(Request $request, string $attempt): JsonResponse
    {
        $model = $this->context->testAttempt($request->user(), $attempt);

        return ApiResponse::sensitive($this->buildResult($model)->toArray());
    }

    private function buildResult(LevelTestAttempt $attempt): TestResultData
    {
        $enrolment = $attempt->levelEnrollment;
        $test = $attempt->levelTest;
        $released = $attempt->resultsAreReleased();

        $used = $enrolment->testAttempts()->where('cycle_number', $enrolment->cycle_number)->count();
        $remaining = $test->allowsUnlimitedAttempts() ? null : max(0, $test->attempt_limit - $used);

        return new TestResultData(
            attemptUuid: $attempt->uuid,
            attemptNumber: $attempt->attempt_number,
            status: $attempt->status->value,
            graded: $attempt->status === TestAttemptStatus::Graded,
            resultsReleased: $released,
            totalQuestions: $attempt->total_questions,
            correctCount: $attempt->correct_count,
            incorrectCount: $attempt->incorrect_count,
            unansweredCount: $attempt->unanswered_count,
            percentage: $attempt->percentage === null ? null : (float) $attempt->percentage,
            passPercentage: (float) $test->pass_percentage,
            passed: $attempt->passed,
            timeSpentSeconds: $attempt->time_spent_seconds,
            submittedAt: $attempt->submitted_at?->toIso8601String(),
            canRetake: $remaining === null || $remaining > 0,
            attemptsRemaining: $remaining,
        );
    }
}

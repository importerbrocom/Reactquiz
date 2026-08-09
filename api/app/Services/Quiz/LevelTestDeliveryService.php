<?php

declare(strict_types=1);

namespace App\Services\Quiz;

use App\Models\LevelTestAnswer;
use App\Models\LevelTestAttempt;
use App\Models\Question;

/**
 * Serves the month-end test in windows.
 *
 * A 300-question paper with four options each is roughly 1–2 MB of JSON. Sending it in
 * one response would stall a phone on a slow connection for many seconds, blow the
 * PWA's cache budget, and hand the whole paper to anyone who opens devtools. So the
 * client fetches a window at a time (config `quiz.test.window_size`, default 20) and
 * prefetches the next one while the student works.
 *
 * The order is read from the attempt's persisted `question_order`, never recomputed, so
 * "question 143" means the same thing on every fetch, on every device, forever.
 */
final class LevelTestDeliveryService
{
    public function __construct(
        private readonly QuestionDeliveryService $delivery,
        private readonly OptionOrderService $orders,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function window(LevelTestAttempt $attempt, int $position = 1, ?int $limit = null): array
    {
        $limit = min(
            $limit ?? (int) config('quiz.test.window_size'),
            (int) config('quiz.test.window_size'),
        );
        $position = max(1, $position);

        /** @var array<int, int> $order */
        $order = $attempt->question_order ?? [];
        $window = array_slice($order, $position - 1, $limit);

        $questions = $this->delivery->load($window);

        // One query for the whole window's saved answers, so resuming a test is two
        // queries regardless of window size.
        $saved = LevelTestAnswer::query()
            ->where('level_test_attempt_id', $attempt->getKey())
            ->whereIn('question_id', $window)
            ->get(['question_id', 'selected_option', 'is_flagged'])
            ->keyBy('question_id');

        $scope = $this->orders->testScope($attempt->getKey());

        $items = [];

        foreach ($window as $offset => $questionId) {
            /** @var Question|null $question */
            $question = $questions->get($questionId);

            if ($question === null) {
                // A question deleted by an admin mid-test. Skipped rather than fatal;
                // grading counts it as unanswered, which cannot hurt the student
                // because there is no negative marking.
                continue;
            }

            /** @var LevelTestAnswer|null $answer */
            $answer = $saved->get($questionId);

            $items[] = $this->delivery->present(
                question: $question,
                position: $position + $offset,
                scope: $scope,
                // Option order is fixed for the whole test attempt: reshuffling options
                // while a student flicks back and forth through the paper would be
                // disorienting and would look like a bug. The per-exposure guarantee is
                // satisfied by the attempt scope — a retake reshuffles everything.
                submissionCount: 0,
                selectedOption: $answer?->selected_option?->value,
                isFlagged: (bool) $answer?->is_flagged,
            );
        }

        return [
            'attempt_uuid' => $attempt->uuid,
            'window' => [
                'from' => $position,
                'to' => $position + max(0, count($window) - 1),
                'size' => count($items),
                'has_more' => ($position - 1 + count($window)) < count($order),
                'next_position' => ($position - 1 + count($window)) < count($order)
                    ? $position + count($window)
                    : null,
            ],
            'total_questions' => $attempt->total_questions,
            'answered_count' => $attempt->answered_count,
            'flagged_count' => $attempt->flagged_count,
            'expires_at' => $attempt->expires_at?->toIso8601String(),
            'seconds_remaining' => $attempt->expires_at === null
                ? null
                : max(0, (int) now()->diffInSeconds($attempt->expires_at, absolute: false)),
            'questions' => $items,
        ];
    }

    /**
     * Compact map of what has been answered, for the question-navigator grid.
     *
     * Deliberately carries no correctness: the grid shows answered / flagged / blank
     * during the test, and nothing more.
     *
     * @return array<int, array{position: int, question_id: int, answered: bool, flagged: bool}>
     */
    public function navigator(LevelTestAttempt $attempt): array
    {
        $saved = LevelTestAnswer::query()
            ->where('level_test_attempt_id', $attempt->getKey())
            ->get(['question_id', 'selected_option', 'is_flagged'])
            ->keyBy('question_id');

        $map = [];

        foreach ($attempt->question_order ?? [] as $offset => $questionId) {
            $answer = $saved->get($questionId);

            $map[] = [
                'position' => $offset + 1,
                'question_id' => (int) $questionId,
                'answered' => $answer?->selected_option !== null,
                'flagged' => (bool) $answer?->is_flagged,
            ];
        }

        return $map;
    }
}

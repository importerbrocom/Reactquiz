<?php

declare(strict_types=1);

namespace App\Services\Quiz;

use App\Models\Question;
use App\Models\QuestionOption;
use App\Support\SeededShuffler;
use Illuminate\Support\Collection;

/**
 * Per-exposure answer-option ordering (docs/adr/003).
 *
 * The order changes every time a question is served AFTER a submission, so a student
 * cannot pass by remembering "the answer is the second one". Crucially it does NOT
 * change while they are looking at the question: the seed includes
 * `submission_count`, which is stable until they answer. A refresh, an offline
 * reload or a resume therefore shows the same order; only being asked again moves it.
 *
 * Nothing is persisted. The stored answer is a canonical option key, so every
 * downstream view (mistake review, results, admin inspection) reconstructs correctly
 * without knowing what order the student happened to see.
 */
final class OptionOrderService
{
    /**
     * Canonical option keys in the order this exposure should display them.
     *
     * @return array<int, string> e.g. ['c', 'a', 'd', 'b']
     */
    public function for(Question $question, int|string $scope, int $submissionCount = 0): array
    {
        /** @var Collection<int, QuestionOption> $options */
        $options = $question->relationLoaded('options')
            ? $question->options
            : $question->options()->get();

        $authored = $options
            ->sortBy(fn (QuestionOption $o): int => $o->display_order)
            ->values();

        if (! $question->shuffle_options) {
            // Options reference each other ("Both 1 and 2"); order carries meaning.
            return $authored->map(fn (QuestionOption $o): string => $o->option_key->value)->all();
        }

        // "All of the above" style options are meaningful only in the last position.
        [$pinned, $shufflable] = $authored->partition(
            fn (QuestionOption $o): bool => $o->pin_last,
        );

        $keys = $shufflable->map(fn (QuestionOption $o): string => $o->option_key->value)->all();

        $shuffled = SeededShuffler::shuffle(
            $keys,
            SeededShuffler::seedFrom($scope, $question->getKey(), $submissionCount),
        );

        return [
            ...$shuffled,
            ...$pinned->map(fn (QuestionOption $o): string => $o->option_key->value)->all(),
        ];
    }

    /**
     * Scope string for a daily-quiz exposure.
     *
     * Derived from the enrolment, day and attempt number rather than the attempt id,
     * so the order is stable even when the day payload is fetched before an attempt
     * row exists.
     */
    public function dailyScope(int $levelEnrollmentId, int $dayNumber, int $attemptNumber): string
    {
        return "d{$levelEnrollmentId}:{$dayNumber}:{$attemptNumber}";
    }

    /** Scope for a month-end test exposure: one exposure per attempt. */
    public function testScope(int $testAttemptId): string
    {
        return "t{$testAttemptId}";
    }
}

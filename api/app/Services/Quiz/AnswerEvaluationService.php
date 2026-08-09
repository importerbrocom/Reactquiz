<?php

declare(strict_types=1);

namespace App\Services\Quiz;

use App\Enums\OptionKey;
use App\Models\Question;

/**
 * Decides whether an answer is correct.
 *
 * Deliberately tiny, and deliberately the only place that comparison happens.
 *
 * The comparison is against `questions.correct_option` — a denormalised copy of the
 * flagged option — so grading is a single primary-key read with no join on the hottest
 * write path in the application. The `question_options` table remains the source of
 * truth; a nightly integrity command proves the two agree
 * (`quiz:verify-question-integrity`).
 *
 * The client submits a canonical option KEY, never a display position, so per-exposure
 * shuffling (docs/adr/003) needs no translation step here at all.
 */
final class AnswerEvaluationService
{
    public function isCorrect(Question $question, OptionKey $selected): bool
    {
        return $question->correct_option === $selected;
    }

    /**
     * Should the correct answer and explanation be revealed?
     *
     * Wrong answers always reveal (business rules 5 and 6 — that is the teaching
     * moment). Correct answers reveal only if the level opts in, so the explanation
     * is not wasted on a student who already knew.
     */
    public function shouldReveal(bool $isCorrect, bool $showExplanationOnCorrect): bool
    {
        return ! $isCorrect || $showExplanationOnCorrect;
    }
}

<?php

declare(strict_types=1);

namespace App\Actions\Quiz;

use App\DTOs\Quiz\AnswerResultData;
use App\DTOs\Quiz\AnswerSubmissionData;
use App\Enums\AttemptStatus;
use App\Enums\QuestionState;
use App\Enums\RetryMode;
use App\Exceptions\AttemptAlreadyCompletedException;
use App\Models\Question;
use App\Models\QuizAttempt;
use App\Models\QuizAttemptAnswer;
use App\Models\QuizAttemptQuestion;
use App\Models\StudentQuestionProgress;
use App\Services\Quiz\AnswerEvaluationService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Records one answer and returns the verdict.
 *
 * The hottest write path in the product, and the one that must never be wrong.
 * Everything is a single-row operation by primary or unique key: no joins, no
 * aggregates, no loops. Target is eight statements.
 *
 * Correctness rests on three things:
 *
 *  1. The attempt row is locked FOR UPDATE, which serialises concurrent submissions
 *     for the same attempt — a double-tap on Submit, or two devices at once.
 *  2. `quiz_attempt_answers` is append-only with a UNIQUE constraint on
 *     (attempt, client_answer_uuid). That is the durable idempotency guarantee: a
 *     replayed offline answer hits a duplicate key and the original verdict is
 *     returned. It holds even if the cache has been flushed.
 *  3. `mastered_count` is incremented only on the FIRST time a question becomes
 *     mastered, so re-answering a correct question cannot inflate the score.
 */
final readonly class SubmitAnswerAction
{
    public function __construct(
        private AnswerEvaluationService $evaluator,
    ) {}

    public function __invoke(QuizAttempt $attempt, AnswerSubmissionData $submission): AnswerResultData
    {
        return DB::transaction(function () use ($attempt, $submission): AnswerResultData {
            // (1) Serialise everything for this attempt.
            /** @var QuizAttempt $locked */
            $locked = QuizAttempt::query()
                ->whereKey($attempt->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status === AttemptStatus::Completed) {
                throw new AttemptAlreadyCompletedException;
            }

            // (2) The question must belong to this attempt. Without this check a
            //     student could submit answers to questions from any other day.
            /** @var QuizAttemptQuestion $state */
            $state = QuizAttemptQuestion::query()
                ->where('quiz_attempt_id', $locked->getKey())
                ->where('question_id', $submission->questionId)
                ->lockForUpdate()
                ->firstOrFail();

            // (3) Grading data by primary key — no join, and the answer-free columns
            //     are never even selected on the delivery path.
            /** @var Question $question */
            $question = Question::query()
                ->whereKey($submission->questionId)
                ->firstOrFail(['id', 'correct_option', 'correct_answer_text', 'explanation']);

            $isCorrect = $this->evaluator->isCorrect($question, $submission->selectedOption);
            $submissionNumber = $state->submission_count + 1;
            $timeSpent = $submission->clampedTimeSpentSeconds();

            // (4) Append to the immutable log. A duplicate client uuid means this is a
            //     replay: return the verdict already recorded rather than double-count.
            try {
                QuizAttemptAnswer::query()->create([
                    'quiz_attempt_id' => $locked->getKey(),
                    'question_id' => $question->getKey(),
                    'client_answer_uuid' => $submission->clientAnswerUuid,
                    'selected_option' => $submission->selectedOption,
                    'is_correct' => $isCorrect,
                    'submission_number' => $submissionNumber,
                    'time_spent_ms' => $submission->timeSpentMs,
                    'answered_at' => $submission->clampedAnsweredAt($locked->started_at ?? now()),
                    'recorded_at' => now(),
                    'was_offline' => $submission->wasOffline,
                ]);
            } catch (UniqueConstraintViolationException) {
                return $this->replayOf($locked, $state, $question, $submission);
            }

            // (5) Per-question state. `mastered` is set once and never unset, so a
            //     later wrong retry cannot take a mastered question back.
            $becameMastered = $isCorrect && $state->state !== QuestionState::Mastered;

            $state->forceFill([
                'selected_option' => $submission->selectedOption,
                'submission_count' => $submissionNumber,
                'time_spent_seconds' => $state->time_spent_seconds + $timeSpent,
                'first_answered_at' => $state->first_answered_at ?? now(),
                'state' => $isCorrect ? QuestionState::Mastered : QuestionState::RetryRequired,
                'wrong_count' => $state->wrong_count + ($isCorrect ? 0 : 1),
                'mastered_at' => $becameMastered ? now() : $state->mastered_at,
            ])->save();

            // (6) Lifetime progress, accumulating across cycles.
            $this->recordLifetimeProgress($locked, $submission, $isCorrect, $submissionNumber, $timeSpent);

            // (7) Attempt counters.
            $locked->forceFill([
                'mastered_count' => $locked->mastered_count + ($becameMastered ? 1 : 0),
                'correct_submissions' => $locked->correct_submissions + ($isCorrect ? 1 : 0),
                'wrong_submissions' => $locked->wrong_submissions + ($isCorrect ? 0 : 1),
                'retry_count' => $locked->retry_count + ($submissionNumber > 1 ? 1 : 0),
                'time_spent_seconds' => $locked->time_spent_seconds + $timeSpent,
                'current_position' => max($locked->current_position, $state->position),
                'last_activity_at' => now(),
            ])->save();

            return $this->result($locked, $state, $question, $isCorrect, $submissionNumber);
        });
    }

    /**
     * Lifetime mastery per student per question.
     *
     * `cycle_first_attempt` is what makes the retention metric possible: comparing
     * first-attempt accuracy in cycle 2 against cycle 1 for the same student and
     * question is the honest measure of whether the product teaches, rather than
     * being memorised item by item.
     */
    private function recordLifetimeProgress(
        QuizAttempt $attempt,
        AnswerSubmissionData $submission,
        bool $isCorrect,
        int $submissionNumber,
        int $timeSpent,
    ): void {
        /** @var StudentQuestionProgress $progress */
        $progress = StudentQuestionProgress::query()->firstOrNew([
            'user_id' => $attempt->user_id,
            'question_id' => $submission->questionId,
        ]);

        $cycleFirstAttempt = $progress->cycle_first_attempt ?? [];
        $cycle = (string) $attempt->cycle_number;

        if ($submissionNumber === 1 && ! array_key_exists($cycle, $cycleFirstAttempt)) {
            $cycleFirstAttempt[$cycle] = $isCorrect;
        }

        $progress->forceFill([
            'level_id' => $attempt->level_id,
            'attempts' => $progress->attempts + 1,
            'correct_count' => $progress->correct_count + ($isCorrect ? 1 : 0),
            'wrong_count' => $progress->wrong_count + ($isCorrect ? 0 : 1),
            'last_selected_option' => $submission->selectedOption,
            'is_mastered' => $progress->is_mastered || $isCorrect,
            'mastered_at' => $progress->mastered_at ?? ($isCorrect ? now() : null),
            'first_attempt_correct' => $progress->first_attempt_correct
                ?? ($submissionNumber === 1 ? $isCorrect : null),
            'cycle_first_attempt' => $cycleFirstAttempt,
            'last_cycle_seen' => $attempt->cycle_number,
            'last_attempted_at' => now(),
            'total_time_seconds' => $progress->total_time_seconds + $timeSpent,
        ])->save();
    }

    /** Rebuild the original verdict for a replayed submission. */
    private function replayOf(
        QuizAttempt $attempt,
        QuizAttemptQuestion $state,
        Question $question,
        AnswerSubmissionData $submission,
    ): AnswerResultData {
        /** @var QuizAttemptAnswer $existing */
        $existing = QuizAttemptAnswer::query()
            ->where('quiz_attempt_id', $attempt->getKey())
            ->where('client_answer_uuid', $submission->clientAnswerUuid)
            ->firstOrFail();

        return $this->result(
            $attempt,
            $state,
            $question,
            $existing->is_correct,
            $existing->submission_number,
            replayed: true,
        );
    }

    private function result(
        QuizAttempt $attempt,
        QuizAttemptQuestion $state,
        Question $question,
        bool $isCorrect,
        int $submissionNumber,
        bool $replayed = false,
    ): AnswerResultData {
        $level = $attempt->level;
        $reveal = $this->evaluator->shouldReveal($isCorrect, (bool) $level->show_explanation_on_correct);

        $outstanding = $attempt->questions()
            ->whereNot('state', QuestionState::Mastered->value)
            ->orderBy('position')
            ->get(['question_id', 'position', 'state']);

        return new AnswerResultData(
            questionId: $question->getKey(),
            isCorrect: $isCorrect,
            submissionNumber: $submissionNumber,
            retryRequired: ! $isCorrect,
            masteredCount: $attempt->mastered_count,
            requiredCount: $attempt->required_count,
            retryRequiredQuestionIds: $outstanding
                ->where('state', QuestionState::RetryRequired)
                ->pluck('question_id')
                ->all(),
            canComplete: $attempt->mastered_count >= $attempt->required_count,
            correctOption: $reveal ? $question->correct_option->value : null,
            correctAnswerText: $reveal ? $question->correct_answer_text : null,
            explanation: $reveal ? $question->explanation : null,
            nextQuestionId: $this->nextQuestionId($level->retry_mode, $state, $outstanding),
            replayed: $replayed,
        );
    }

    /**
     * Which question to serve next.
     *
     * In `requeue_at_end` (the default) a wrong answer does NOT come back immediately:
     * the student sees the correct answer and explanation, then moves on, and the
     * question returns after the others. That gap is what turns recognition into
     * recall — without it, "mastered" only proves the student can click the answer
     * they were just shown (docs/adr/003, fix 2).
     *
     * @param  Collection<int, QuizAttemptQuestion>  $outstanding
     */
    private function nextQuestionId(
        RetryMode $retryMode,
        QuizAttemptQuestion $current,
        $outstanding,
    ): ?int {
        if ($outstanding->isEmpty()) {
            return null;
        }

        if ($retryMode === RetryMode::Immediate && $current->needsRetry()) {
            return $current->question_id;
        }

        // The next outstanding question after this position, wrapping around.
        $after = $outstanding->firstWhere(
            fn (QuizAttemptQuestion $row): bool => $row->position > $current->position,
        );

        return ($after ?? $outstanding->first())->question_id;
    }
}

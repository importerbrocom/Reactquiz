<?php

declare(strict_types=1);

namespace App\DTOs\Quiz;

/**
 * The verdict returned after a submission.
 *
 * The answer fields are populated ONLY when the student has earned the right to see
 * them — on a wrong answer, or on a correct one if the level opts in. Building that
 * decision into the DTO means no controller can leak them by forgetting a condition.
 */
final readonly class AnswerResultData
{
    public function __construct(
        public int $questionId,
        public bool $isCorrect,
        public int $submissionNumber,
        public bool $retryRequired,
        public int $masteredCount,
        public int $requiredCount,
        public array $retryRequiredQuestionIds,
        public bool $canComplete,
        public ?string $correctOption = null,
        public ?string $correctAnswerText = null,
        public ?string $explanation = null,
        public ?int $nextQuestionId = null,
        public bool $replayed = false,
    ) {}

    public function toArray(): array
    {
        return array_filter([
            'question_id' => $this->questionId,
            'is_correct' => $this->isCorrect,
            'submission_number' => $this->submissionNumber,
            'retry_required' => $this->retryRequired,
            // Present only when revealed; absent entirely otherwise.
            'correct_option' => $this->correctOption,
            'correct_answer_text' => $this->correctAnswerText,
            'explanation' => $this->explanation,
            'next_question_id' => $this->nextQuestionId,
            'progress' => [
                'mastered_count' => $this->masteredCount,
                'required_count' => $this->requiredCount,
                'retry_required_question_ids' => $this->retryRequiredQuestionIds,
            ],
            'can_complete' => $this->canComplete,
            // Lets the client tell "recorded just now" from "we already had this one",
            // which is what the offline outbox needs in order to drop a queued answer
            // rather than keep retrying it.
            'replayed' => $this->replayed,
        ], static fn (mixed $value): bool => $value !== null);
    }
}

<?php

declare(strict_types=1);

namespace App\DTOs\Quiz;

final readonly class TestSyncResultData
{
    public function __construct(
        public int $accepted,
        public int $rejected,
        public int $answeredCount,
        public int $flaggedCount,
        public int $totalQuestions,
        public int $currentPosition,
        public ?string $expiresAt,
        public int $secondsRemaining,
        public bool $replayed = false,
        /** @var array<int, int> question ids that are not part of this attempt */
        public array $rejectedQuestionIds = [],
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'accepted' => $this->accepted,
            'rejected' => $this->rejected,
            'rejected_question_ids' => $this->rejectedQuestionIds,
            'replayed' => $this->replayed,
            'progress' => [
                'answered_count' => $this->answeredCount,
                'flagged_count' => $this->flaggedCount,
                'total_questions' => $this->totalQuestions,
                'current_position' => $this->currentPosition,
            ],
            'expires_at' => $this->expiresAt,
            'seconds_remaining' => $this->secondsRemaining,
        ];
    }
}

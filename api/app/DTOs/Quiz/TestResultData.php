<?php

declare(strict_types=1);

namespace App\DTOs\Quiz;

final readonly class TestResultData
{
    public function __construct(
        public string $attemptUuid,
        public int $attemptNumber,
        public string $status,
        public bool $graded,
        public bool $resultsReleased,
        public ?int $totalQuestions = null,
        public ?int $correctCount = null,
        public ?int $incorrectCount = null,
        public ?int $unansweredCount = null,
        public ?float $percentage = null,
        public ?float $passPercentage = null,
        public ?bool $passed = null,
        public ?int $timeSpentSeconds = null,
        public ?string $submittedAt = null,
        public bool $canRetake = false,
        public ?int $attemptsRemaining = null,
        public bool $levelCompleted = false,
        public ?int $nextLevel = null,
        public ?int $nextCycle = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $payload = [
            'attempt_uuid' => $this->attemptUuid,
            'attempt_number' => $this->attemptNumber,
            'status' => $this->status,
            'graded' => $this->graded,
            'results_released' => $this->resultsReleased,
            'submitted_at' => $this->submittedAt,
        ];

        // Withheld entirely until release, so a scraped response cannot be scored.
        if ($this->resultsReleased) {
            $payload['score'] = [
                'total_questions' => $this->totalQuestions,
                'correct' => $this->correctCount,
                'incorrect' => $this->incorrectCount,
                'unanswered' => $this->unansweredCount,
                'percentage' => $this->percentage,
                'pass_percentage' => $this->passPercentage,
                'passed' => $this->passed,
            ];
            $payload['time_spent_seconds'] = $this->timeSpentSeconds;
            $payload['can_retake'] = $this->canRetake;
            $payload['attempts_remaining'] = $this->attemptsRemaining;
            $payload['progression'] = [
                'level_completed' => $this->levelCompleted,
                'next_level' => $this->nextLevel,
                'next_cycle' => $this->nextCycle,
            ];
        }

        return $payload;
    }
}

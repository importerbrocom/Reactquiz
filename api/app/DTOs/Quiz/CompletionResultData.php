<?php

declare(strict_types=1);

namespace App\DTOs\Quiz;

final readonly class CompletionResultData
{
    public function __construct(
        public int $dayNumber,
        public int $score,
        public int $requiredCount,
        public int $completedDays,
        public int $totalDays,
        public int $timeSpentSeconds,
        public int $currentStreak,
        public int $longestStreak,
        public ?int $nextDay,
        public bool $nextDayUnlocked,
        public ?string $nextDayUnlocksAt,
        public ?string $nextDayHint,
        public bool $levelTestUnlocked,
        public bool $alreadyCompleted = false,
    ) {}

    public function toArray(): array
    {
        return [
            'day_number' => $this->dayNumber,
            'score' => $this->score,
            'required_count' => $this->requiredCount,
            'time_spent_seconds' => $this->timeSpentSeconds,
            'progress' => [
                'completed_days' => $this->completedDays,
                'total_days' => $this->totalDays,
            ],
            'streak' => [
                'current' => $this->currentStreak,
                'longest' => $this->longestStreak,
            ],
            'next_day' => $this->nextDay === null ? null : array_filter([
                'day' => $this->nextDay,
                'unlocked' => $this->nextDayUnlocked,
                'unlocks_at' => $this->nextDayUnlocksAt,
                'hint' => $this->nextDayHint,
            ], static fn (mixed $v): bool => $v !== null),
            'level_test_unlocked' => $this->levelTestUnlocked,
            'already_completed' => $this->alreadyCompleted,
        ];
    }
}

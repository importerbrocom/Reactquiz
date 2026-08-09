<?php

declare(strict_types=1);

namespace App\DTOs\Quiz;

use App\Enums\OptionKey;
use Carbon\CarbonImmutable;

/**
 * One answer inside a month-end test batch.
 *
 * Note what is absent: correctness. During a test the server records the CHOICE and
 * nothing else — `is_correct` stays NULL in the database until grading. That is what
 * makes the sync endpoint incapable of leaking the answer key, however it is called.
 */
final readonly class TestAnswerData
{
    public function __construct(
        public int $questionId,
        public ?OptionKey $selectedOption,
        public bool $isFlagged = false,
        public ?int $timeSpentMs = null,
        public ?CarbonImmutable $answeredAt = null,
    ) {}

    /** @param array<string, mixed> $row */
    public static function fromArray(array $row): self
    {
        $option = $row['selected_option'] ?? null;

        return new self(
            questionId: (int) $row['question_id'],
            // An explicit null clears a previous choice: students change their minds.
            selectedOption: $option === null || $option === '' ? null : OptionKey::from((string) $option),
            isFlagged: (bool) ($row['is_flagged'] ?? false),
            timeSpentMs: isset($row['time_spent_ms']) ? (int) $row['time_spent_ms'] : null,
            answeredAt: isset($row['answered_at'])
                ? CarbonImmutable::parse((string) $row['answered_at'])
                : null,
        );
    }

    /** Clamped so a wrong device clock cannot poison timing analytics. */
    public function clampedTimeSpentMs(): ?int
    {
        if ($this->timeSpentMs === null) {
            return null;
        }

        return max(0, min($this->timeSpentMs, 3_600_000));
    }
}

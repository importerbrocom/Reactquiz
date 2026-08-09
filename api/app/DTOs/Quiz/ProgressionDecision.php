<?php

declare(strict_types=1);

namespace App\DTOs\Quiz;

use Carbon\CarbonInterface;

/**
 * Whether a student may move on from the level they are on, and to what.
 */
final readonly class ProgressionDecision
{
    private function __construct(
        public bool $allowed,
        public ?string $reason = null,
        public ?string $message = null,
        public ?int $targetLevelNumber = null,
        public ?int $targetCycle = null,
        public bool $programmeCompleted = false,
        public ?int $nextProgrammeId = null,
        public ?CarbonInterface $unlocksAt = null,
        /** @var array<string, mixed> */
        public array $context = [],
    ) {}

    public static function advance(
        int $targetLevelNumber,
        int $targetCycle,
        ?CarbonInterface $unlocksAt = null,
    ): self {
        return new self(
            allowed: true,
            targetLevelNumber: $targetLevelNumber,
            targetCycle: $targetCycle,
            unlocksAt: $unlocksAt,
        );
    }

    /** The whole programme is finished — there is nothing above this level. */
    public static function finished(?int $nextProgrammeId = null): self
    {
        return new self(
            allowed: false,
            reason: 'programme_completed',
            message: 'You have finished every level in this programme.',
            programmeCompleted: true,
            nextProgrammeId: $nextProgrammeId,
        );
    }

    /** @param array<string, mixed> $context */
    public static function deny(string $reason, string $message, array $context = []): self
    {
        return new self(allowed: false, reason: $reason, message: $message, context: $context);
    }

    public function denied(): bool
    {
        return ! $this->allowed;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_filter([
            'allowed' => $this->allowed,
            'reason' => $this->reason,
            'message' => $this->message,
            'next_level' => $this->targetLevelNumber,
            'next_cycle' => $this->targetCycle,
            'programme_completed' => $this->programmeCompleted,
            'next_programme_id' => $this->nextProgrammeId,
            'unlocks_at' => $this->unlocksAt?->toIso8601String(),
            'context' => $this->context === [] ? null : $this->context,
        ], static fn (mixed $value): bool => $value !== null && $value !== false);
    }
}

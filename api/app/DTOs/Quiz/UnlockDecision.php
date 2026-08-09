<?php

declare(strict_types=1);

namespace App\DTOs\Quiz;

use Carbon\CarbonInterface;

/**
 * The result of an unlock check.
 *
 * Carries a machine-readable reason plus a human sentence, because the student needs
 * to be told *how* to unlock the day, not merely that they cannot have it. A bare
 * boolean would force every caller to reconstruct that message.
 */
final readonly class UnlockDecision
{
    private function __construct(
        public bool $allowed,
        public ?string $reason = null,
        public ?string $message = null,
        public ?int $blockingDay = null,
        public ?CarbonInterface $unlocksAt = null,
        public array $context = [],
    ) {}

    public static function allow(): self
    {
        return new self(allowed: true);
    }

    public static function deny(
        string $reason,
        string $message,
        ?int $blockingDay = null,
        ?CarbonInterface $unlocksAt = null,
        array $context = [],
    ): self {
        return new self(
            allowed: false,
            reason: $reason,
            message: $message,
            blockingDay: $blockingDay,
            unlocksAt: $unlocksAt,
            context: $context,
        );
    }

    public function denied(): bool
    {
        return ! $this->allowed;
    }

    /** Shape handed to the client inside the 423 response's `meta`. */
    public function meta(): array
    {
        return array_filter([
            'reason' => $this->reason,
            'unlock_hint' => $this->message,
            'blocking_day' => $this->blockingDay,
            'unlocks_at' => $this->unlocksAt?->toIso8601String(),
            ...$this->context,
        ], static fn (mixed $value): bool => $value !== null);
    }
}

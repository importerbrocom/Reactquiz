<?php

declare(strict_types=1);

namespace App\DTOs\Quiz;

use App\Enums\OptionKey;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

/**
 * One answer as submitted by a client.
 *
 * Note what is absent: no `is_correct`, no `score`, no `mastered`. The client states
 * what it chose and nothing about the outcome — those are the server's to decide
 * (business rules 17-20). Fields the client must not control simply have no home here.
 */
final readonly class AnswerSubmissionData
{
    public function __construct(
        public int $questionId,
        public OptionKey $selectedOption,
        public string $clientAnswerUuid,
        public ?int $timeSpentMs = null,
        public ?CarbonImmutable $answeredAt = null,
        public bool $wasOffline = false,
    ) {}

    public static function fromArray(array $payload): self
    {
        return new self(
            questionId: (int) $payload['question_id'],
            selectedOption: OptionKey::from(mb_strtolower((string) $payload['selected_option'])),
            clientAnswerUuid: (string) $payload['client_answer_uuid'],
            timeSpentMs: isset($payload['time_spent_ms']) ? (int) $payload['time_spent_ms'] : null,
            answeredAt: isset($payload['answered_at'])
                ? CarbonImmutable::parse((string) $payload['answered_at'])
                : null,
            wasOffline: (bool) ($payload['was_offline'] ?? false),
        );
    }

    /**
     * Client clocks cannot be trusted — a device may be hours out, or the value may
     * be spoofed to fake a fast answer. Clamp into a plausible window around the
     * attempt; `recorded_at` remains the server's own truth either way.
     */
    public function clampedAnsweredAt(Carbon $attemptStartedAt): Carbon
    {
        $tolerance = config('quiz.answered_at_tolerance');
        $earliest = $attemptStartedAt->copy()->subMinutes((int) $tolerance['past_minutes']);
        $latest = now()->addMinutes((int) $tolerance['future_minutes']);

        $value = $this->answeredAt !== null ? Carbon::instance($this->answeredAt->toDateTime()) : now();

        return $value->lessThan($earliest) ? $earliest
            : ($value->greaterThan($latest) ? now() : $value);
    }

    public function clampedTimeSpentSeconds(): int
    {
        // An hour on one question is already implausible; anything beyond is noise.
        return max(0, min((int) round(($this->timeSpentMs ?? 0) / 1000), 3600));
    }
}

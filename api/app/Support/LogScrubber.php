<?php

declare(strict_types=1);

namespace App\Support;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Strips PII and secrets from log records before they reach any handler.
 *
 * Per docs/phase-1/08-security-architecture.md §9:
 * "A LogScrubber processor strips password, password_confirmation, token,
 * access_token, refresh_token, Authorization, Cookie, endpoint, p256dh, auth
 * from every log record and Sentry event."
 */
class LogScrubber implements ProcessorInterface
{
    private const REDACT_KEYS = [
        'password',
        'password_confirmation',
        'current_password',
        'token',
        'access_token',
        'refresh_token',
        'Authorization',
        'Cookie',
        'endpoint',
        'p256dh',
        'auth_token',
        'auth',
        'secret',
        'private_key',
    ];

    private const REPLACEMENT = '***REDACTED***';

    public function __invoke(LogRecord $record): LogRecord
    {
        $context = $this->scrubArray($record->context);
        $extra = $this->scrubArray($record->extra);

        return $record->with(context: $context, extra: $extra);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function scrubArray(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && $this->shouldRedact($key)) {
                $data[$key] = self::REPLACEMENT;
            } elseif (is_array($value)) {
                $data[$key] = $this->scrubArray($value);
            }
        }

        return $data;
    }

    private function shouldRedact(string $key): bool
    {
        $lower = strtolower($key);

        foreach (self::REDACT_KEYS as $redactKey) {
            if (str_contains($lower, strtolower($redactKey))) {
                return true;
            }
        }

        return false;
    }
}

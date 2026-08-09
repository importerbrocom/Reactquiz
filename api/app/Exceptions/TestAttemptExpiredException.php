<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Thrown when the server-side deadline has passed.
 *
 * The attempt is closed and graded on whatever was answered before this is raised, so
 * the client's next move is to fetch the result — not to retry the sync.
 */
final class TestAttemptExpiredException extends ApiException
{
    protected int $status = 409;

    protected string $errorCode = 'TEST_ATTEMPT_EXPIRED';

    protected function defaultMessage(): string
    {
        return 'Time is up. Your test was submitted with the answers received so far.';
    }
}

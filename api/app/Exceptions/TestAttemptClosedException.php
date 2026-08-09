<?php

declare(strict_types=1);

namespace App\Exceptions;

/** Thrown when answers or a submission arrive for a test that is already finished. */
final class TestAttemptClosedException extends ApiException
{
    protected int $status = 409;

    protected string $errorCode = 'TEST_ATTEMPT_CLOSED';

    protected function defaultMessage(): string
    {
        return 'This test has already been submitted.';
    }
}

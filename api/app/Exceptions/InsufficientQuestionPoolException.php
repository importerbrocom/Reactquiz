<?php

declare(strict_types=1);

namespace App\Exceptions;

/** Guarded earlier by the level Readiness check; this is the last line of defence. */
final class InsufficientQuestionPoolException extends ApiException
{
    protected int $status = 422;

    protected string $errorCode = 'INSUFFICIENT_QUESTION_POOL';

    protected function defaultMessage(): string
    {
        return 'This level does not yet have enough questions to be started.';
    }
}

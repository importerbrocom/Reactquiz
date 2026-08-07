<?php

declare(strict_types=1);

namespace App\Exceptions;

final class AttemptAlreadyCompletedException extends ApiException
{
    protected int $status = 409;

    protected string $errorCode = 'ATTEMPT_ALREADY_COMPLETED';

    protected function defaultMessage(): string
    {
        return 'This attempt has already been completed.';
    }
}

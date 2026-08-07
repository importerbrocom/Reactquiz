<?php

declare(strict_types=1);

namespace App\Exceptions;

final class AccountLockedException extends ApiException
{
    protected int $status = 423;

    protected string $errorCode = 'ACCOUNT_LOCKED';

    protected function defaultMessage(): string
    {
        return 'Too many failed attempts. Please try again later.';
    }
}

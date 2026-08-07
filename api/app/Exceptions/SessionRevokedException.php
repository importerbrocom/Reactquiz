<?php

declare(strict_types=1);

namespace App\Exceptions;

/** Refresh-token reuse detected, or all sessions invalidated. Client must hard-logout. */
final class SessionRevokedException extends ApiException
{
    protected int $status = 401;

    protected string $errorCode = 'SESSION_REVOKED';

    protected function defaultMessage(): string
    {
        return 'Your session has been revoked. Please sign in again.';
    }
}

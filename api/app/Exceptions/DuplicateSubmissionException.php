<?php

declare(strict_types=1);

namespace App\Exceptions;

final class DuplicateSubmissionException extends ApiException
{
    protected int $status = 409;

    protected string $errorCode = 'DUPLICATE_SUBMISSION';

    protected function defaultMessage(): string
    {
        return 'A different request is already in flight with this idempotency key.';
    }
}

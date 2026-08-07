<?php

declare(strict_types=1);

namespace App\Exceptions;

final class EnrolmentInactiveException extends ApiException
{
    protected int $status = 403;

    protected string $errorCode = 'ENROLMENT_INACTIVE';

    protected function defaultMessage(): string
    {
        return 'You do not have an active enrolment for this programme.';
    }
}

<?php

declare(strict_types=1);

namespace App\Exceptions;

final class ImportValidationException extends ApiException
{
    protected int $status = 422;

    protected string $errorCode = 'IMPORT_VALIDATION_FAILED';

    protected function defaultMessage(): string
    {
        return 'The uploaded file could not be processed.';
    }
}

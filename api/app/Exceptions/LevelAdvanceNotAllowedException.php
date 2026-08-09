<?php

declare(strict_types=1);

namespace App\Exceptions;

/** Thrown when a student asks to move to the next level before earning it. */
final class LevelAdvanceNotAllowedException extends ApiException
{
    protected int $status = 423;

    protected string $errorCode = 'LEVEL_ADVANCE_NOT_ALLOWED';

    protected function defaultMessage(): string
    {
        return 'This level is not finished yet.';
    }
}

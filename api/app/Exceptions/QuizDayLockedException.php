<?php

declare(strict_types=1);

namespace App\Exceptions;

/** Sequential-unlock violation. 423 (not 403) so the client can show "not yet" rather than "never". */
final class QuizDayLockedException extends ApiException
{
    protected int $status = 423;

    protected string $errorCode = 'QUIZ_DAY_LOCKED';

    protected function defaultMessage(): string
    {
        return 'This quiz day is locked. Complete the previous day with full marks to unlock it.';
    }
}

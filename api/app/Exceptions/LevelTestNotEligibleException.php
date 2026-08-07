<?php

declare(strict_types=1);

namespace App\Exceptions;

/** Fewer than total_quiz_days completed at full score. */
final class LevelTestNotEligibleException extends ApiException
{
    protected int $status = 423;

    protected string $errorCode = 'LEVEL_TEST_NOT_ELIGIBLE';

    protected function defaultMessage(): string
    {
        return 'Complete every daily quiz with full marks before taking the month-end test.';
    }
}

<?php

declare(strict_types=1);

namespace App\Exceptions;

/** Thrown when /complete is called but the server-side 10/10 re-check fails. */
final class QuizNotCompleteException extends ApiException
{
    protected int $status = 422;

    protected string $errorCode = 'QUIZ_NOT_COMPLETE';

    protected function defaultMessage(): string
    {
        return 'Every question must be answered correctly before this day can be completed.';
    }
}

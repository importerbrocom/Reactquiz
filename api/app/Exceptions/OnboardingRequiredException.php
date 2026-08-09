<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * The student has not chosen an exam and programme yet.
 *
 * Deliberately a separate code from EnrolmentInactiveException. Both mean "you have no
 * usable enrolment", but the client's response differs completely: this one sends the
 * student to onboarding, whereas an inactive enrolment means something is wrong with
 * their account and they should be told to contact support. One shared code would force
 * the frontend to guess from the message text.
 */
final class OnboardingRequiredException extends ApiException
{
    protected int $status = 403;

    protected string $errorCode = 'ONBOARDING_REQUIRED';

    protected function defaultMessage(): string
    {
        return 'Choose your exam to get started.';
    }
}

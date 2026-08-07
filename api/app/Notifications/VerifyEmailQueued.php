<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\QueueName;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Queued so registration returns immediately instead of waiting on SMTP.
 * Sending mail inline is one of the easiest ways to make sign-up feel slow.
 */
final class VerifyEmailQueued extends VerifyEmail implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        $this->onQueue(QueueName::Mail->value);
    }
}

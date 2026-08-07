<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\QueueName;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

final class ResetPasswordQueued extends ResetPassword implements ShouldQueue
{
    use Queueable;

    public function __construct(string $token)
    {
        parent::__construct($token);
        $this->onQueue(QueueName::Mail->value);
    }
}

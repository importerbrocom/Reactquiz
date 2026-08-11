<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\QueueName;
use App\Models\PushSubscription;
use App\Services\Push\WebPushService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Sends a push notification to a single subscription.
 * Dispatched per-subscription during campaign fan-out or event-driven notifications.
 */
class SendPushNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        private readonly PushSubscription $subscription,
        private readonly array $payload,
        private readonly ?string $notificationId = null,
    ) {
        $this->onQueue(QueueName::Notifications->value);
    }

    public function handle(WebPushService $pushService): void
    {
        $pushService->sendToSubscription(
            $this->subscription,
            $this->payload,
            $this->notificationId,
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Push;

use App\Enums\NotificationStatus;
use App\Enums\PushStatus;
use App\Models\NotificationDelivery;
use App\Models\PushSubscription;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\MessageSentReport;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/**
 * Sends Web Push notifications via the VAPID protocol.
 *
 * Handles delivery tracking, failure counting, and endpoint expiry.
 * One notification fans out to all active subscriptions for a user.
 */
class WebPushService
{
    private WebPush $webPush;

    public function __construct()
    {
        $auth = [
            'VAPID' => [
                'subject' => config('services.webpush.subject'),
                'publicKey' => config('services.webpush.public_key'),
                'privateKey' => config('services.webpush.private_key'),
            ],
        ];

        $this->webPush = new WebPush($auth);
        $this->webPush->setReuseVAPIDHeaders(true);
        $this->webPush->setAutomaticPadding(2048);
    }

    /**
     * Send a push payload to a single subscription.
     *
     * @param  array<string, mixed>  $payload  JSON-serialisable push data
     */
    public function sendToSubscription(
        PushSubscription $sub,
        array $payload,
        ?string $notificationId = null,
    ): bool {
        $subscription = Subscription::create([
            'endpoint' => $sub->endpoint,
            'publicKey' => $sub->public_key,
            'authToken' => $sub->auth_token,
            'contentEncoding' => $sub->content_encoding,
        ]);

        $jsonPayload = json_encode($payload, JSON_THROW_ON_ERROR);

        $this->webPush->queueNotification($subscription, $jsonPayload);

        /** @var MessageSentReport $report */
        foreach ($this->webPush->flush() as $report) {
            return $this->handleReport($report, $sub, $notificationId);
        }

        return false;
    }

    /**
     * Send to all active subscriptions for a user.
     *
     * @param  array<string, mixed>  $payload
     * @return array{sent: int, failed: int}
     */
    public function sendToUser(
        int $userId,
        array $payload,
        ?string $notificationId = null,
    ): array {
        $subscriptions = PushSubscription::where('user_id', $userId)
            ->active()
            ->get();

        $sent = 0;
        $failed = 0;

        foreach ($subscriptions as $sub) {
            $success = $this->sendToSubscription($sub, $payload, $notificationId);
            $success ? $sent++ : $failed++;
        }

        return ['sent' => $sent, 'failed' => $failed];
    }

    private function handleReport(
        MessageSentReport $report,
        PushSubscription $sub,
        ?string $notificationId,
    ): bool {
        $success = $report->isSuccess();
        $httpCode = $report->getResponse()?->getStatusCode();

        // Track delivery
        if ($notificationId) {
            NotificationDelivery::create([
                'notification_id' => $notificationId,
                'push_subscription_id' => $sub->id,
                'channel' => 'webpush',
                'status' => $success
                    ? NotificationStatus::Sent
                    : NotificationStatus::Failed,
                'http_status' => $httpCode,
                'error_code' => $success ? null : $report->getReason(),
                'dispatched_at' => now(),
                'settled_at' => now(),
            ]);
        }

        // Update subscription health
        if ($success) {
            $sub->update([
                'failure_count' => 0,
                'last_success_at' => now(),
            ]);
        } else {
            $newFailureCount = $sub->failure_count + 1;
            $updates = [
                'failure_count' => $newFailureCount,
                'last_failure_at' => now(),
            ];

            // Expire the subscription after 3 consecutive failures with 410 Gone
            if ($httpCode === 410 || $newFailureCount >= 3) {
                $updates['status'] = PushStatus::Expired;
                Log::info('Push subscription expired', ['id' => $sub->id, 'user' => $sub->user_id]);
            }

            $sub->update($updates);
        }

        return $success;
    }
}

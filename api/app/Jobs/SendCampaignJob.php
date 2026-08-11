<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\PushStatus;
use App\Enums\QueueName;
use App\Models\NotificationCampaign;
use App\Models\PushSubscription;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;

/**
 * Fan-out a notification campaign to all targeted subscriptions.
 * Uses Laravel Bus::batch() so the admin can track progress.
 */
class SendCampaignJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly NotificationCampaign $campaign,
    ) {
        $this->onQueue(QueueName::Notifications->value);
    }

    public function handle(): void
    {
        $campaign = $this->campaign;
        $campaign->update(['status' => 'sending']);

        // Build target query based on audience
        $query = PushSubscription::where('status', PushStatus::Active);

        if ($campaign->audience !== 'all' && $campaign->audience_filter) {
            // Apply audience filters (programme, level, etc.)
            if (isset($campaign->audience_filter['programme_id'])) {
                $query->whereHas('user.programmeEnrollments', function ($q) use ($campaign) {
                    $q->where('programme_id', $campaign->audience_filter['programme_id']);
                });
            }
        }

        $subscriptions = $query->get();
        $campaign->update(['target_count' => $subscriptions->count()]);

        // Dispatch individual push jobs as a batch
        $jobs = $subscriptions->map(function (PushSubscription $sub) use ($campaign) {
            return new SendPushNotificationJob($sub, [
                'title' => $campaign->title,
                'body' => $campaign->body,
                'icon' => '/icons/icon-192.png',
                'badge' => '/icons/icon-192.png',
                'tag' => "campaign-{$campaign->id}",
                'data' => [
                    'type' => 'announcement',
                    'url' => $campaign->action_url ?? '/notifications',
                    'campaign_id' => $campaign->id,
                ],
            ]);
        })->all();

        if (empty($jobs)) {
            $campaign->update(['status' => 'sent', 'sent_count' => 0]);

            return;
        }

        $batch = Bus::batch($jobs)
            ->name("Campaign #{$campaign->id}: {$campaign->title}")
            ->onQueue(QueueName::Notifications->value)
            ->finally(function () use ($campaign) {
                $campaign->refresh();
                $campaign->update(['status' => 'sent']);
            })
            ->dispatch();

        $campaign->update(['batch_id' => $batch->id]);
    }
}

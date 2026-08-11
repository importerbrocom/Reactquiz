<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\PushStatus;
use App\Enums\QueueName;
use App\Models\PushSubscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Prune expired/dead push subscriptions.
 *
 * Criteria:
 * - Status = expired, or
 * - failure_count >= 3 and last_success_at is null or older than 30 days, or
 * - last_seen_at older than 90 days (device abandoned)
 */
class PruneExpiredSubscriptionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
        $this->onQueue(QueueName::Notifications->value);
    }

    public function handle(): void
    {
        $pruned = PushSubscription::where(function ($q) {
            $q->where('status', PushStatus::Expired)
                ->orWhere(function ($q2) {
                    $q2->where('failure_count', '>=', 3)
                        ->where(function ($q3) {
                            $q3->whereNull('last_success_at')
                                ->orWhere('last_success_at', '<', now()->subDays(30));
                        });
                })
                ->orWhere('last_seen_at', '<', now()->subDays(90));
        })->delete();

        Log::info("Pruned {$pruned} expired push subscriptions.");
    }
}

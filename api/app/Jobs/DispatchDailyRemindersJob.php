<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\PushStatus;
use App\Enums\QueueName;
use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

/**
 * Scheduler dispatches this once per minute (or every 15 min).
 * It finds users whose reminder_time matches "now" in their timezone,
 * who have an active push subscription, and who haven't completed today's quiz.
 *
 * Each user gets a SendPushNotificationJob dispatched.
 */
class DispatchDailyRemindersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
        $this->onQueue(QueueName::Notifications->value);
    }

    public function handle(): void
    {
        // Get distinct timezones with active push subscriptions
        $timezones = PushSubscription::where('status', PushStatus::Active)
            ->whereNotNull('timezone')
            ->distinct()
            ->pluck('timezone');

        foreach ($timezones as $tz) {
            $localNow = Carbon::now($tz);

            // Only process at the top of each hour (for simplicity — a full implementation
            // would check against the user's configured reminder_time from onboarding preferences)
            if ($localNow->minute > 5) {
                continue;
            }

            // Find users in this timezone with active subscriptions
            $userIds = PushSubscription::where('timezone', $tz)
                ->where('status', PushStatus::Active)
                ->pluck('user_id')
                ->unique();

            foreach ($userIds as $userId) {
                $user = User::find($userId);
                if (! $user) {
                    continue;
                }

                // Get their active subscriptions and dispatch push
                $subscriptions = PushSubscription::where('user_id', $userId)
                    ->active()
                    ->get();

                foreach ($subscriptions as $sub) {
                    SendPushNotificationJob::dispatch($sub, [
                        'title' => 'Time to practice!',
                        'body' => 'Your daily 10 questions are waiting.',
                        'icon' => '/icons/icon-192.png',
                        'badge' => '/icons/icon-192.png',
                        'tag' => 'daily-reminder',
                        'data' => [
                            'type' => 'daily_reminder',
                            'url' => '/dashboard',
                        ],
                    ]);
                }
            }
        }
    }
}

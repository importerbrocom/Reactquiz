<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\NotificationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $notification_id
 * @property int|null $push_subscription_id
 * @property string $channel
 * @property NotificationStatus $status
 * @property int|null $http_status
 * @property string|null $error_code
 * @property int $attempt
 * @property Carbon|null $dispatched_at
 * @property Carbon|null $settled_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read PushSubscription|null $subscription
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationDelivery newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationDelivery newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationDelivery query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationDelivery whereAttempt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationDelivery whereChannel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationDelivery whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationDelivery whereDispatchedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationDelivery whereErrorCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationDelivery whereHttpStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationDelivery whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationDelivery whereNotificationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationDelivery wherePushSubscriptionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationDelivery whereSettledAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationDelivery whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationDelivery whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class NotificationDelivery extends Model
{
    protected $fillable = [
        'notification_id', 'push_subscription_id', 'channel', 'status',
        'http_status', 'error_code', 'attempt', 'dispatched_at', 'settled_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => NotificationStatus::class,
            'http_status' => 'integer',
            'attempt' => 'integer',
            'dispatched_at' => 'datetime',
            'settled_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<PushSubscription, $this> */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(PushSubscription::class, 'push_subscription_id');
    }
}

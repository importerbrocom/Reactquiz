<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\NotificationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(PushSubscription::class, 'push_subscription_id');
    }
}

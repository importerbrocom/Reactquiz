<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PushStatus;
use App\Enums\PushTransport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property PushTransport $transport
 * @property string|null $endpoint
 * @property string|null $endpoint_hash
 * @property string|null $public_key
 * @property string|null $auth_token
 * @property string $content_encoding
 * @property string|null $device_token
 * @property string|null $device_label
 * @property string|null $user_agent
 * @property string|null $browser
 * @property string|null $platform
 * @property string|null $timezone
 * @property PushStatus $status
 * @property int $failure_count
 * @property Carbon|null $last_success_at
 * @property Carbon|null $last_failure_at
 * @property Carbon|null $last_seen_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, NotificationDelivery> $deliveries
 * @property-read int|null $deliveries_count
 * @property-read User|null $user
 *
 * @method static Builder<static>|PushSubscription active()
 * @method static Builder<static>|PushSubscription newModelQuery()
 * @method static Builder<static>|PushSubscription newQuery()
 * @method static Builder<static>|PushSubscription query()
 * @method static Builder<static>|PushSubscription whereAuthToken($value)
 * @method static Builder<static>|PushSubscription whereBrowser($value)
 * @method static Builder<static>|PushSubscription whereContentEncoding($value)
 * @method static Builder<static>|PushSubscription whereCreatedAt($value)
 * @method static Builder<static>|PushSubscription whereDeviceLabel($value)
 * @method static Builder<static>|PushSubscription whereDeviceToken($value)
 * @method static Builder<static>|PushSubscription whereEndpoint($value)
 * @method static Builder<static>|PushSubscription whereEndpointHash($value)
 * @method static Builder<static>|PushSubscription whereFailureCount($value)
 * @method static Builder<static>|PushSubscription whereId($value)
 * @method static Builder<static>|PushSubscription whereLastFailureAt($value)
 * @method static Builder<static>|PushSubscription whereLastSeenAt($value)
 * @method static Builder<static>|PushSubscription whereLastSuccessAt($value)
 * @method static Builder<static>|PushSubscription wherePlatform($value)
 * @method static Builder<static>|PushSubscription wherePublicKey($value)
 * @method static Builder<static>|PushSubscription whereStatus($value)
 * @method static Builder<static>|PushSubscription whereTimezone($value)
 * @method static Builder<static>|PushSubscription whereTransport($value)
 * @method static Builder<static>|PushSubscription whereUpdatedAt($value)
 * @method static Builder<static>|PushSubscription whereUserAgent($value)
 * @method static Builder<static>|PushSubscription whereUserId($value)
 *
 * @mixin \Eloquent
 */
class PushSubscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'transport', 'endpoint', 'endpoint_hash', 'public_key', 'auth_token',
        'content_encoding', 'device_token', 'device_label', 'user_agent', 'browser',
        'platform', 'timezone', 'status', 'last_seen_at',
    ];

    protected $hidden = ['public_key', 'auth_token', 'endpoint', 'device_token'];

    protected function casts(): array
    {
        return [
            'transport' => PushTransport::class,
            'status' => PushStatus::class,
            'failure_count' => 'integer',
            'last_success_at' => 'datetime',
            'last_failure_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<NotificationDelivery, $this> */
    public function deliveries(): HasMany
    {
        return $this->hasMany(NotificationDelivery::class);
    }

    public static function hashEndpoint(string $endpoint): string
    {
        return hash('sha256', $endpoint);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', PushStatus::Active->value);
    }
}

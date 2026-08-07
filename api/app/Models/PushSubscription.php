<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PushStatus;
use App\Enums\PushTransport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

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

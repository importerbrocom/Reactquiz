<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\RefreshTokenFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $family_id
 * @property string $token_hash
 * @property int|null $access_token_id
 * @property string|null $device_name
 * @property string|null $device_hash
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property Carbon $expires_at
 * @property Carbon|null $last_used_at
 * @property Carbon|null $rotated_at
 * @property Carbon|null $revoked_at
 * @property string|null $revoked_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 *
 * @method static \Database\Factories\RefreshTokenFactory factory($count = null, $state = [])
 * @method static Builder<static>|RefreshToken newModelQuery()
 * @method static Builder<static>|RefreshToken newQuery()
 * @method static Builder<static>|RefreshToken query()
 * @method static Builder<static>|RefreshToken usable()
 * @method static Builder<static>|RefreshToken whereAccessTokenId($value)
 * @method static Builder<static>|RefreshToken whereCreatedAt($value)
 * @method static Builder<static>|RefreshToken whereDeviceHash($value)
 * @method static Builder<static>|RefreshToken whereDeviceName($value)
 * @method static Builder<static>|RefreshToken whereExpiresAt($value)
 * @method static Builder<static>|RefreshToken whereFamilyId($value)
 * @method static Builder<static>|RefreshToken whereId($value)
 * @method static Builder<static>|RefreshToken whereIpAddress($value)
 * @method static Builder<static>|RefreshToken whereLastUsedAt($value)
 * @method static Builder<static>|RefreshToken whereRevokedAt($value)
 * @method static Builder<static>|RefreshToken whereRevokedReason($value)
 * @method static Builder<static>|RefreshToken whereRotatedAt($value)
 * @method static Builder<static>|RefreshToken whereTokenHash($value)
 * @method static Builder<static>|RefreshToken whereUpdatedAt($value)
 * @method static Builder<static>|RefreshToken whereUserAgent($value)
 * @method static Builder<static>|RefreshToken whereUserId($value)
 *
 * @mixin \Eloquent
 */
class RefreshToken extends Model
{
    /** @use HasFactory<RefreshTokenFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id', 'family_id', 'token_hash', 'access_token_id',
        'device_name', 'device_hash', 'ip_address', 'user_agent', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'last_used_at' => 'datetime',
            'rotated_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isUsable(): bool
    {
        return $this->revoked_at === null
            && $this->rotated_at === null
            && $this->expires_at->isFuture();
    }

    /** A rotated token being presented again means it leaked. */
    public function wasAlreadyRotated(): bool
    {
        return $this->rotated_at !== null;
    }

    public function scopeUsable(Builder $query): Builder
    {
        return $query->whereNull('revoked_at')
            ->whereNull('rotated_at')
            ->where('expires_at', '>', now());
    }
}

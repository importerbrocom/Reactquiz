<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\RefreshTokenFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

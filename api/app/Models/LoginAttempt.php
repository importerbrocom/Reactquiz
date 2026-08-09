<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $email_hash
 * @property int|null $user_id
 * @property string $ip_address
 * @property bool $successful
 * @property string|null $failure_reason
 * @property string|null $user_agent
 * @property Carbon|null $created_at
 * @property-read User|null $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoginAttempt newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoginAttempt newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoginAttempt query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoginAttempt whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoginAttempt whereEmailHash($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoginAttempt whereFailureReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoginAttempt whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoginAttempt whereIpAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoginAttempt whereSuccessful($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoginAttempt whereUserAgent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoginAttempt whereUserId($value)
 *
 * @mixin \Eloquent
 */
class LoginAttempt extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'email_hash', 'user_id', 'ip_address', 'successful', 'failure_reason', 'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'successful' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function hashEmail(string $email): string
    {
        return hash('sha256', mb_strtolower(trim($email)));
    }
}

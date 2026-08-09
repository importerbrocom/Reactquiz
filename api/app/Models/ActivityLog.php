<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ActivitySeverity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $event
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property array<array-key, mixed>|null $properties
 * @property string|null $reason
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property ActivitySeverity $severity
 * @property Carbon|null $created_at
 * @property-read Model|\Eloquent|null $subject
 * @property-read User|null $user
 *
 * @method static Builder<static>|ActivityLog newModelQuery()
 * @method static Builder<static>|ActivityLog newQuery()
 * @method static Builder<static>|ActivityLog query()
 * @method static Builder<static>|ActivityLog security()
 * @method static Builder<static>|ActivityLog whereCreatedAt($value)
 * @method static Builder<static>|ActivityLog whereEvent($value)
 * @method static Builder<static>|ActivityLog whereId($value)
 * @method static Builder<static>|ActivityLog whereIpAddress($value)
 * @method static Builder<static>|ActivityLog whereProperties($value)
 * @method static Builder<static>|ActivityLog whereReason($value)
 * @method static Builder<static>|ActivityLog whereSeverity($value)
 * @method static Builder<static>|ActivityLog whereSubjectId($value)
 * @method static Builder<static>|ActivityLog whereSubjectType($value)
 * @method static Builder<static>|ActivityLog whereUserAgent($value)
 * @method static Builder<static>|ActivityLog whereUserId($value)
 *
 * @mixin \Eloquent
 */
class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id', 'event', 'subject_type', 'subject_id', 'properties',
        'reason', 'ip_address', 'user_agent', 'severity',
    ];

    protected function casts(): array
    {
        return [
            'properties' => 'array',
            'severity' => ActivitySeverity::class,
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeSecurity(Builder $query): Builder
    {
        return $query->where('severity', ActivitySeverity::Security->value);
    }
}

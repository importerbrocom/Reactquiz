<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ActivitySeverity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

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

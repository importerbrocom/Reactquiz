<?php

declare(strict_types=1);

namespace App\Services\Support;

use App\Enums\ActivitySeverity;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;

/**
 * Business audit trail. Distinct from application logs: this is queryable by
 * admins and is where "who reopened this student's day, and why" lives.
 */
final class ActivityLogger
{
    /** @param  array<string, mixed>  $properties */
    public function log(
        string $event,
        ?User $user = null,
        ?Model $subject = null,
        array $properties = [],
        string $severity = 'info',
        ?string $reason = null,
    ): ActivityLog {
        return ActivityLog::query()->create([
            'user_id' => $user?->getKey(),
            'event' => $event,
            'subject_type' => $subject !== null ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'properties' => $properties === [] ? null : $properties,
            'reason' => $reason,
            'ip_address' => Request::ip(),
            'user_agent' => str((string) Request::userAgent())->limit(250, '')->toString(),
            'severity' => ActivitySeverity::tryFrom($severity) ?? ActivitySeverity::Info,
        ]);
    }

    /** @param  array<string, mixed>  $properties */
    public function security(string $event, ?User $user = null, array $properties = []): ActivityLog
    {
        return $this->log($event, $user, properties: $properties, severity: 'security');
    }
}

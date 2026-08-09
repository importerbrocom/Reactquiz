<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $created_by
 * @property string $title
 * @property string $body
 * @property string|null $action_url
 * @property string $audience
 * @property array<array-key, mixed>|null $audience_filter
 * @property string $status
 * @property Carbon|null $scheduled_for
 * @property int $target_count
 * @property int $sent_count
 * @property int $failed_count
 * @property string|null $batch_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $creator
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationCampaign newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationCampaign newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationCampaign query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationCampaign whereActionUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationCampaign whereAudience($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationCampaign whereAudienceFilter($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationCampaign whereBatchId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationCampaign whereBody($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationCampaign whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationCampaign whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationCampaign whereFailedCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationCampaign whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationCampaign whereScheduledFor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationCampaign whereSentCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationCampaign whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationCampaign whereTargetCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationCampaign whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationCampaign whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class NotificationCampaign extends Model
{
    use HasFactory;

    protected $fillable = [
        'created_by', 'title', 'body', 'action_url', 'audience',
        'audience_filter', 'status', 'scheduled_for',
    ];

    protected function casts(): array
    {
        return [
            'audience_filter' => 'array',
            'scheduled_for' => 'datetime',
            'target_count' => 'integer',
            'sent_count' => 'integer',
            'failed_count' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

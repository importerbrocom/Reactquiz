<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ReportFormat;
use App\Enums\ReportStatus;
use App\Enums\ReportType;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportExport extends Model
{
    use HasUuid;

    protected $fillable = [
        'requested_by', 'type', 'format', 'filters', 'status', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => ReportType::class,
            'format' => ReportFormat::class,
            'status' => ReportStatus::class,
            'filters' => 'array',
            'row_count' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'expires_at' => 'datetime',
            'downloaded_at' => 'datetime',
        ];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function hasExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}

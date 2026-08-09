<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ReportFormat;
use App\Enums\ReportStatus;
use App\Enums\ReportType;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property int $requested_by
 * @property ReportType $type
 * @property ReportFormat $format
 * @property array<array-key, mixed>|null $filters
 * @property ReportStatus $status
 * @property int|null $row_count
 * @property string|null $storage_disk
 * @property string|null $storage_path
 * @property int|null $file_size_bytes
 * @property string|null $error_message
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $downloaded_at
 * @property int $download_count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $requester
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReportExport newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReportExport newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReportExport query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReportExport whereCompletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReportExport whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReportExport whereDownloadCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReportExport whereDownloadedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReportExport whereErrorMessage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReportExport whereExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReportExport whereFileSizeBytes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReportExport whereFilters($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReportExport whereFormat($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReportExport whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReportExport whereRequestedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReportExport whereRowCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReportExport whereStartedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReportExport whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReportExport whereStorageDisk($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReportExport whereStoragePath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReportExport whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReportExport whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReportExport whereUuid($value)
 *
 * @mixin \Eloquent
 */
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

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function hasExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}

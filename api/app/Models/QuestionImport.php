<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ImportStatus;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property int|null $level_id
 * @property int $uploaded_by
 * @property string $original_filename
 * @property string $storage_disk
 * @property string $storage_path
 * @property string $mime_type
 * @property int $file_size_bytes
 * @property string $file_checksum
 * @property ImportStatus $status
 * @property int $progress_percent
 * @property int $rows_total
 * @property int $rows_valid
 * @property int $rows_warning
 * @property int $rows_rejected
 * @property int $rows_duplicate
 * @property int $rows_imported
 * @property string|null $failure_reason
 * @property string|null $failure_detail
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $approved_at
 * @property int|null $approved_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, QuestionImportItem> $items
 * @property-read int|null $items_count
 * @property-read Level|null $level
 * @property-read Collection<int, Question> $questions
 * @property-read int|null $questions_count
 * @property-read User|null $uploader
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionImport newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionImport newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionImport onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionImport query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionImport whereApprovedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionImport whereApprovedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionImport whereCompletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionImport whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionImport whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionImport whereFailureDetail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionImport whereFailureReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionImport whereFileChecksum($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionImport whereFileSizeBytes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionImport whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionImport whereLevelId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionImport whereMimeType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionImport whereOriginalFilename($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionImport whereProgressPercent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionImport whereRowsDuplicate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionImport whereRowsImported($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionImport whereRowsRejected($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionImport whereRowsTotal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionImport whereRowsValid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionImport whereRowsWarning($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionImport whereStartedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionImport whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionImport whereStorageDisk($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionImport whereStoragePath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionImport whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionImport whereUploadedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionImport whereUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionImport withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionImport withoutTrashed()
 *
 * @mixin \Eloquent
 */
class QuestionImport extends Model
{
    use HasFactory, HasUuid, SoftDeletes;

    protected $fillable = [
        'level_id', 'uploaded_by', 'original_filename', 'storage_disk', 'storage_path',
        'mime_type', 'file_size_bytes', 'file_checksum', 'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => ImportStatus::class,
            'progress_percent' => 'integer',
            'rows_total' => 'integer',
            'rows_valid' => 'integer',
            'rows_warning' => 'integer',
            'rows_rejected' => 'integer',
            'rows_duplicate' => 'integer',
            'rows_imported' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Level, $this> */
    public function level(): BelongsTo
    {
        return $this->belongsTo(Level::class);
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /** @return HasMany<QuestionImportItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(QuestionImportItem::class);
    }

    /** @return HasMany<Question, $this> */
    public function questions(): HasMany
    {
        return $this->hasMany(Question::class, 'question_import_id');
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, [
            ImportStatus::Completed,
            ImportStatus::PartiallyCompleted,
            ImportStatus::Failed,
        ], true);
    }
}

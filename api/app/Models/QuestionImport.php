<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ImportStatus;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

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

    public function level(): BelongsTo
    {
        return $this->belongsTo(Level::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuestionImportItem::class);
    }

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

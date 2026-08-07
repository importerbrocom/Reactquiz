<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ImportItemStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionImportItem extends Model
{
    protected $fillable = [
        'question_import_id', 'row_number', 'external_ref', 'question_text',
        'option_a', 'option_b', 'option_c', 'option_d', 'correct_option', 'explanation',
        'topic', 'tags', 'source', 'image_filename', 'row_status', 'status',
        'errors', 'warnings', 'question_hash', 'duplicate_of_question_id',
        'created_question_id', 'edited_by', 'edited_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ImportItemStatus::class,
            'errors' => 'array',
            'warnings' => 'array',
            'row_number' => 'integer',
            'edited_at' => 'datetime',
        ];
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(QuestionImport::class, 'question_import_id');
    }

    public function duplicateOf(): BelongsTo
    {
        return $this->belongsTo(Question::class, 'duplicate_of_question_id');
    }

    public function createdQuestion(): BelongsTo
    {
        return $this->belongsTo(Question::class, 'created_question_id');
    }

    public function isImportable(): bool
    {
        return in_array($this->status, [
            ImportItemStatus::Valid,
            ImportItemStatus::Warning,
        ], true);
    }

    /** @return array<int, string> */
    public function optionTexts(): array
    {
        return array_values(array_filter([
            $this->option_a, $this->option_b, $this->option_c, $this->option_d,
        ], static fn (?string $v): bool => $v !== null && $v !== ''));
    }

    public function scopeImportable(Builder $query): Builder
    {
        return $query->whereIn('status', [
            ImportItemStatus::Valid->value,
            ImportItemStatus::Warning->value,
        ]);
    }
}

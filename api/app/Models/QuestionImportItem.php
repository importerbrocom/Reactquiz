<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ImportItemStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $question_import_id
 * @property int $row_number
 * @property string|null $external_ref
 * @property string|null $question_text
 * @property string|null $option_a
 * @property string|null $option_b
 * @property string|null $option_c
 * @property string|null $option_d
 * @property string|null $correct_option
 * @property string|null $explanation
 * @property string|null $topic
 * @property string|null $tags
 * @property string|null $source
 * @property string|null $image_filename
 * @property string|null $row_status
 * @property ImportItemStatus $status
 * @property array<array-key, mixed>|null $errors
 * @property array<array-key, mixed>|null $warnings
 * @property string|null $question_hash
 * @property int|null $duplicate_of_question_id
 * @property int|null $created_question_id
 * @property int|null $edited_by
 * @property Carbon|null $edited_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Question|null $createdQuestion
 * @property-read Question|null $duplicateOf
 * @property-read QuestionImport|null $import
 *
 * @method static Builder<static>|QuestionImportItem importable()
 * @method static Builder<static>|QuestionImportItem newModelQuery()
 * @method static Builder<static>|QuestionImportItem newQuery()
 * @method static Builder<static>|QuestionImportItem query()
 * @method static Builder<static>|QuestionImportItem whereCorrectOption($value)
 * @method static Builder<static>|QuestionImportItem whereCreatedAt($value)
 * @method static Builder<static>|QuestionImportItem whereCreatedQuestionId($value)
 * @method static Builder<static>|QuestionImportItem whereDuplicateOfQuestionId($value)
 * @method static Builder<static>|QuestionImportItem whereEditedAt($value)
 * @method static Builder<static>|QuestionImportItem whereEditedBy($value)
 * @method static Builder<static>|QuestionImportItem whereErrors($value)
 * @method static Builder<static>|QuestionImportItem whereExplanation($value)
 * @method static Builder<static>|QuestionImportItem whereExternalRef($value)
 * @method static Builder<static>|QuestionImportItem whereId($value)
 * @method static Builder<static>|QuestionImportItem whereImageFilename($value)
 * @method static Builder<static>|QuestionImportItem whereOptionA($value)
 * @method static Builder<static>|QuestionImportItem whereOptionB($value)
 * @method static Builder<static>|QuestionImportItem whereOptionC($value)
 * @method static Builder<static>|QuestionImportItem whereOptionD($value)
 * @method static Builder<static>|QuestionImportItem whereQuestionHash($value)
 * @method static Builder<static>|QuestionImportItem whereQuestionImportId($value)
 * @method static Builder<static>|QuestionImportItem whereQuestionText($value)
 * @method static Builder<static>|QuestionImportItem whereRowNumber($value)
 * @method static Builder<static>|QuestionImportItem whereRowStatus($value)
 * @method static Builder<static>|QuestionImportItem whereSource($value)
 * @method static Builder<static>|QuestionImportItem whereStatus($value)
 * @method static Builder<static>|QuestionImportItem whereTags($value)
 * @method static Builder<static>|QuestionImportItem whereTopic($value)
 * @method static Builder<static>|QuestionImportItem whereUpdatedAt($value)
 * @method static Builder<static>|QuestionImportItem whereWarnings($value)
 *
 * @mixin \Eloquent
 */
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

    /** @return BelongsTo<QuestionImport, $this> */
    public function import(): BelongsTo
    {
        return $this->belongsTo(QuestionImport::class, 'question_import_id');
    }

    /** @return BelongsTo<Question, $this> */
    public function duplicateOf(): BelongsTo
    {
        return $this->belongsTo(Question::class, 'duplicate_of_question_id');
    }

    /** @return BelongsTo<Question, $this> */
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

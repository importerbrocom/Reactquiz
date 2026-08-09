<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ContentStatus;
use App\Enums\OptionKey;
use Database\Factories\QuestionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $level_id
 * @property string $question_text
 * @property string|null $question_image_path
 * @property int|null $question_image_width
 * @property int|null $question_image_height
 * @property string|null $question_image_alt
 * @property OptionKey $correct_option
 * @property string|null $correct_answer_text
 * @property string $explanation
 * @property string|null $topic
 * @property array<array-key, mixed>|null $tags
 * @property string|null $source
 * @property ContentStatus $status
 * @property string $question_hash
 * @property bool $shuffle_options
 * @property int $times_served
 * @property int $times_correct
 * @property int $times_incorrect
 * @property numeric|null $observed_difficulty
 * @property int|null $question_import_id
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read QuestionImport|null $import
 * @property-read Level|null $level
 * @property-read Collection<int, QuestionOption> $options
 * @property-read int|null $options_count
 * @property-read Collection<int, StudentQuestionProgress> $studentProgress
 * @property-read int|null $student_progress_count
 *
 * @method static Builder<static>|Question active()
 * @method static \Database\Factories\QuestionFactory factory($count = null, $state = [])
 * @method static Builder<static>|Question forLevel(int $levelId)
 * @method static Builder<static>|Question newModelQuery()
 * @method static Builder<static>|Question newQuery()
 * @method static Builder<static>|Question onlyTrashed()
 * @method static Builder<static>|Question query()
 * @method static Builder<static>|Question whereCorrectAnswerText($value)
 * @method static Builder<static>|Question whereCorrectOption($value)
 * @method static Builder<static>|Question whereCreatedAt($value)
 * @method static Builder<static>|Question whereCreatedBy($value)
 * @method static Builder<static>|Question whereDeletedAt($value)
 * @method static Builder<static>|Question whereExplanation($value)
 * @method static Builder<static>|Question whereId($value)
 * @method static Builder<static>|Question whereLevelId($value)
 * @method static Builder<static>|Question whereObservedDifficulty($value)
 * @method static Builder<static>|Question whereQuestionHash($value)
 * @method static Builder<static>|Question whereQuestionImageAlt($value)
 * @method static Builder<static>|Question whereQuestionImageHeight($value)
 * @method static Builder<static>|Question whereQuestionImagePath($value)
 * @method static Builder<static>|Question whereQuestionImageWidth($value)
 * @method static Builder<static>|Question whereQuestionImportId($value)
 * @method static Builder<static>|Question whereQuestionText($value)
 * @method static Builder<static>|Question whereShuffleOptions($value)
 * @method static Builder<static>|Question whereSource($value)
 * @method static Builder<static>|Question whereStatus($value)
 * @method static Builder<static>|Question whereTags($value)
 * @method static Builder<static>|Question whereTimesCorrect($value)
 * @method static Builder<static>|Question whereTimesIncorrect($value)
 * @method static Builder<static>|Question whereTimesServed($value)
 * @method static Builder<static>|Question whereTopic($value)
 * @method static Builder<static>|Question whereUpdatedAt($value)
 * @method static Builder<static>|Question whereUpdatedBy($value)
 * @method static Builder<static>|Question withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|Question withoutTrashed()
 *
 * @mixin \Eloquent
 */
class Question extends Model
{
    /** @use HasFactory<QuestionFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'level_id', 'question_text', 'question_image_path', 'question_image_width',
        'question_image_height', 'question_image_alt', 'correct_option', 'correct_answer_text',
        'explanation', 'topic', 'tags', 'source', 'status', 'question_hash',
        'shuffle_options', 'question_import_id', 'created_by', 'updated_by',
    ];

    /**
     * Defence in depth for the single most valuable asset in the product.
     *
     * The student-facing API Resource is a positive whitelist, but hiding these here
     * as well means an accidental response()->json($question) or ->toArray() cannot
     * leak an answer either. QuestionAdminResource calls makeVisible() explicitly.
     */
    protected $hidden = [
        'correct_option',
        'correct_answer_text',
        'explanation',
    ];

    protected function casts(): array
    {
        return [
            'status' => ContentStatus::class,
            'correct_option' => OptionKey::class,
            'tags' => 'array',
            'shuffle_options' => 'boolean',
            'observed_difficulty' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Level, $this> */
    public function level(): BelongsTo
    {
        return $this->belongsTo(Level::class);
    }

    /** @return HasMany<QuestionOption, $this> */
    public function options(): HasMany
    {
        return $this->hasMany(QuestionOption::class)->orderBy('display_order');
    }

    /** @return BelongsTo<QuestionImport, $this> */
    public function import(): BelongsTo
    {
        return $this->belongsTo(QuestionImport::class, 'question_import_id');
    }

    /** @return HasMany<StudentQuestionProgress, $this> */
    public function studentProgress(): HasMany
    {
        return $this->hasMany(StudentQuestionProgress::class);
    }

    // -------------------------------------------------------------- helpers --

    /**
     * Canonical hash used for deduplication: normalised stem plus the *sorted* set
     * of option texts, so reordered options and casing/whitespace differences are
     * still detected as duplicates while a genuine rewording is not.
     *
     * @param  array<int, string>  $optionTexts
     */
    public static function makeHash(string $questionText, array $optionTexts): string
    {
        $normalise = static fn (string $value): string => Str::of($value)
            ->stripTags()
            ->lower()
            ->replaceMatches('/\s+/u', ' ')
            ->trim()
            ->toString();

        $options = array_map($normalise, $optionTexts);
        sort($options, SORT_STRING);

        return hash('sha256', $normalise($questionText).'|'.implode('|', $options));
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', ContentStatus::Active->value);
    }

    public function scopeForLevel(Builder $query, int $levelId): Builder
    {
        return $query->where('level_id', $levelId);
    }
}

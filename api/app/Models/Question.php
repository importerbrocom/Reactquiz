<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ContentStatus;
use App\Enums\OptionKey;
use Database\Factories\QuestionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

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

    public function level(): BelongsTo
    {
        return $this->belongsTo(Level::class);
    }

    /** @return HasMany<QuestionOption, $this> */
    public function options(): HasMany
    {
        return $this->hasMany(QuestionOption::class)->orderBy('display_order');
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(QuestionImport::class, 'question_import_id');
    }

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

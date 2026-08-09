<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OptionKey;
use App\Support\OptionTextAnalyser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $question_id
 * @property OptionKey $option_key
 * @property string $option_text
 * @property string|null $option_image_path
 * @property bool $is_correct
 * @property int $display_order
 * @property bool $pin_last
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $option_image_alt
 * @property-read Question|null $question
 *
 * @method static \Database\Factories\QuestionOptionFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionOption newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionOption newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionOption query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionOption whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionOption whereDisplayOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionOption whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionOption whereIsCorrect($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionOption whereOptionImageAlt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionOption whereOptionImagePath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionOption whereOptionKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionOption whereOptionText($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionOption wherePinLast($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionOption whereQuestionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionOption whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class QuestionOption extends Model
{
    use HasFactory;

    protected $fillable = [
        'question_id', 'option_key', 'option_text', 'option_image_path', 'option_image_alt',
        'is_correct', 'display_order', 'pin_last',
    ];

    /** is_correct never reaches a student payload. */
    protected $hidden = ['is_correct'];

    protected function casts(): array
    {
        return [
            'option_key' => OptionKey::class,
            'is_correct' => 'boolean',
            'pin_last' => 'boolean',
            'display_order' => 'integer',
        ];
    }

    /** @return BelongsTo<Question, $this> */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    /**
     * Shuffle-safety checks, delegated to App\Support\OptionTextAnalyser so the
     * patterns live in one auditable place and are shared with the import pipeline.
     */
    public static function textReferencesOtherOptions(string $text): bool
    {
        return OptionTextAnalyser::referencesOtherOptions($text);
    }

    public static function isAllOrNoneOfTheAbove(string $text): bool
    {
        return OptionTextAnalyser::shouldPinLast($text);
    }
}

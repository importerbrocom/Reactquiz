<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ContentStatus;
use Database\Factories\DailyQuizFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $level_id
 * @property int $day_number
 * @property string|null $title
 * @property int $question_count
 * @property ContentStatus $status
 * @property Carbon|null $available_from
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, QuizAttempt> $attempts
 * @property-read int|null $attempts_count
 * @property-read Level|null $level
 *
 * @method static Builder<static>|DailyQuiz active()
 * @method static \Database\Factories\DailyQuizFactory factory($count = null, $state = [])
 * @method static Builder<static>|DailyQuiz newModelQuery()
 * @method static Builder<static>|DailyQuiz newQuery()
 * @method static Builder<static>|DailyQuiz onlyTrashed()
 * @method static Builder<static>|DailyQuiz query()
 * @method static Builder<static>|DailyQuiz whereAvailableFrom($value)
 * @method static Builder<static>|DailyQuiz whereCreatedAt($value)
 * @method static Builder<static>|DailyQuiz whereDayNumber($value)
 * @method static Builder<static>|DailyQuiz whereDeletedAt($value)
 * @method static Builder<static>|DailyQuiz whereId($value)
 * @method static Builder<static>|DailyQuiz whereLevelId($value)
 * @method static Builder<static>|DailyQuiz whereQuestionCount($value)
 * @method static Builder<static>|DailyQuiz whereStatus($value)
 * @method static Builder<static>|DailyQuiz whereTitle($value)
 * @method static Builder<static>|DailyQuiz whereUpdatedAt($value)
 * @method static Builder<static>|DailyQuiz withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|DailyQuiz withoutTrashed()
 *
 * @mixin \Eloquent
 */
class DailyQuiz extends Model
{
    /** @use HasFactory<DailyQuizFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'level_id', 'day_number', 'title', 'question_count', 'status', 'available_from',
    ];

    protected function casts(): array
    {
        return [
            'status' => ContentStatus::class,
            'available_from' => 'date',
            'day_number' => 'integer',
            'question_count' => 'integer',
        ];
    }

    /** @return BelongsTo<Level, $this> */
    public function level(): BelongsTo
    {
        return $this->belongsTo(Level::class);
    }

    /** @return HasMany<QuizAttempt, $this> */
    public function attempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', ContentStatus::Active->value);
    }
}

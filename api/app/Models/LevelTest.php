<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ContentStatus;
use Database\Factories\LevelTestFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $level_id
 * @property int $version
 * @property int $day_number
 * @property string|null $title
 * @property int $question_count
 * @property numeric $pass_percentage
 * @property int|null $time_limit_minutes
 * @property int $attempt_limit
 * @property bool $shuffle_questions
 * @property bool $allow_pause
 * @property ContentStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, LevelTestAttempt> $attempts
 * @property-read int|null $attempts_count
 * @property-read Level|null $level
 *
 * @method static \Database\Factories\LevelTestFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LevelTest newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LevelTest newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LevelTest query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LevelTest whereAllowPause($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LevelTest whereAttemptLimit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LevelTest whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LevelTest whereDayNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LevelTest whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LevelTest whereLevelId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LevelTest wherePassPercentage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LevelTest whereQuestionCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LevelTest whereShuffleQuestions($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LevelTest whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LevelTest whereTimeLimitMinutes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LevelTest whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LevelTest whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LevelTest whereVersion($value)
 *
 * @mixin \Eloquent
 */
class LevelTest extends Model
{
    /** @use HasFactory<LevelTestFactory> */
    use HasFactory;

    protected $fillable = [
        'level_id', 'version', 'day_number', 'title', 'question_count', 'pass_percentage',
        'time_limit_minutes', 'attempt_limit', 'shuffle_questions', 'allow_pause', 'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => ContentStatus::class,
            'shuffle_questions' => 'boolean',
            'allow_pause' => 'boolean',
            'pass_percentage' => 'decimal:2',
            'attempt_limit' => 'integer',
            'question_count' => 'integer',
        ];
    }

    /** @return BelongsTo<Level, $this> */
    public function level(): BelongsTo
    {
        return $this->belongsTo(Level::class);
    }

    /** @return HasMany<LevelTestAttempt, $this> */
    public function attempts(): HasMany
    {
        return $this->hasMany(LevelTestAttempt::class);
    }

    public function allowsUnlimitedAttempts(): bool
    {
        return $this->attempt_limit === 0;
    }
}

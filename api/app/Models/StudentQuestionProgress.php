<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OptionKey;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Lifetime mastery per student per question, accumulating across cycles.
 *
 * @property int $id
 * @property int $user_id
 * @property int $level_id
 * @property int $question_id
 * @property int $attempts
 * @property int $correct_count
 * @property int $wrong_count
 * @property OptionKey|null $last_selected_option
 * @property bool $is_mastered
 * @property Carbon|null $mastered_at
 * @property bool|null $first_attempt_correct
 * @property array<array-key, mixed>|null $cycle_first_attempt
 * @property int $last_cycle_seen
 * @property Carbon|null $last_attempted_at
 * @property int $total_time_seconds
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Level|null $level
 * @property-read Question|null $question
 * @property-read User|null $user
 *
 * @method static Builder<static>|StudentQuestionProgress everWrong()
 * @method static Builder<static>|StudentQuestionProgress newModelQuery()
 * @method static Builder<static>|StudentQuestionProgress newQuery()
 * @method static Builder<static>|StudentQuestionProgress query()
 * @method static Builder<static>|StudentQuestionProgress unresolved()
 * @method static Builder<static>|StudentQuestionProgress whereAttempts($value)
 * @method static Builder<static>|StudentQuestionProgress whereCorrectCount($value)
 * @method static Builder<static>|StudentQuestionProgress whereCreatedAt($value)
 * @method static Builder<static>|StudentQuestionProgress whereCycleFirstAttempt($value)
 * @method static Builder<static>|StudentQuestionProgress whereFirstAttemptCorrect($value)
 * @method static Builder<static>|StudentQuestionProgress whereId($value)
 * @method static Builder<static>|StudentQuestionProgress whereIsMastered($value)
 * @method static Builder<static>|StudentQuestionProgress whereLastAttemptedAt($value)
 * @method static Builder<static>|StudentQuestionProgress whereLastCycleSeen($value)
 * @method static Builder<static>|StudentQuestionProgress whereLastSelectedOption($value)
 * @method static Builder<static>|StudentQuestionProgress whereLevelId($value)
 * @method static Builder<static>|StudentQuestionProgress whereMasteredAt($value)
 * @method static Builder<static>|StudentQuestionProgress whereQuestionId($value)
 * @method static Builder<static>|StudentQuestionProgress whereTotalTimeSeconds($value)
 * @method static Builder<static>|StudentQuestionProgress whereUpdatedAt($value)
 * @method static Builder<static>|StudentQuestionProgress whereUserId($value)
 * @method static Builder<static>|StudentQuestionProgress whereWrongCount($value)
 *
 * @mixin \Eloquent
 */
class StudentQuestionProgress extends Model
{
    protected $table = 'student_question_progress';

    protected $fillable = [
        'user_id', 'level_id', 'question_id', 'attempts', 'correct_count', 'wrong_count',
        'last_selected_option', 'is_mastered', 'mastered_at', 'first_attempt_correct',
        'cycle_first_attempt', 'last_cycle_seen', 'last_attempted_at', 'total_time_seconds',
    ];

    protected function casts(): array
    {
        return [
            'last_selected_option' => OptionKey::class,
            'is_mastered' => 'boolean',
            'first_attempt_correct' => 'boolean',
            'cycle_first_attempt' => 'array',
            'attempts' => 'integer',
            'correct_count' => 'integer',
            'wrong_count' => 'integer',
            'last_cycle_seen' => 'integer',
            'total_time_seconds' => 'integer',
            'mastered_at' => 'datetime',
            'last_attempted_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Question, $this> */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    /** @return BelongsTo<Level, $this> */
    public function level(): BelongsTo
    {
        return $this->belongsTo(Level::class);
    }

    /** Retention signal: was this right first time in the given cycle? */
    public function firstAttemptCorrectInCycle(int $cycle): ?bool
    {
        $value = $this->cycle_first_attempt[$cycle] ?? null;

        return $value === null ? null : (bool) $value;
    }

    public function scopeUnresolved(Builder $query): Builder
    {
        return $query->where('wrong_count', '>', 0)->where('is_mastered', false);
    }

    public function scopeEverWrong(Builder $query): Builder
    {
        return $query->where('wrong_count', '>', 0);
    }
}

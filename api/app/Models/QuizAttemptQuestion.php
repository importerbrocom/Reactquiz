<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OptionKey;
use App\Enums\QuestionState;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Per-question state inside an attempt.
 *
 * `submission_count` doubles as the per-exposure option-shuffle seed input, which
 * is how docs/adr/003 is implemented with no additional storage.
 *
 * @property int $id
 * @property int $quiz_attempt_id
 * @property int $question_id
 * @property int $position
 * @property QuestionState $state
 * @property OptionKey|null $selected_option
 * @property int $wrong_count
 * @property int $submission_count
 * @property int $time_spent_seconds
 * @property Carbon|null $first_answered_at
 * @property Carbon|null $mastered_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read QuizAttempt $attempt
 * @property-read Question|null $question
 *
 * @method static Builder<static>|QuizAttemptQuestion mastered()
 * @method static Builder<static>|QuizAttemptQuestion newModelQuery()
 * @method static Builder<static>|QuizAttemptQuestion newQuery()
 * @method static Builder<static>|QuizAttemptQuestion outstanding()
 * @method static Builder<static>|QuizAttemptQuestion query()
 * @method static Builder<static>|QuizAttemptQuestion whereCreatedAt($value)
 * @method static Builder<static>|QuizAttemptQuestion whereFirstAnsweredAt($value)
 * @method static Builder<static>|QuizAttemptQuestion whereId($value)
 * @method static Builder<static>|QuizAttemptQuestion whereMasteredAt($value)
 * @method static Builder<static>|QuizAttemptQuestion wherePosition($value)
 * @method static Builder<static>|QuizAttemptQuestion whereQuestionId($value)
 * @method static Builder<static>|QuizAttemptQuestion whereQuizAttemptId($value)
 * @method static Builder<static>|QuizAttemptQuestion whereSelectedOption($value)
 * @method static Builder<static>|QuizAttemptQuestion whereState($value)
 * @method static Builder<static>|QuizAttemptQuestion whereSubmissionCount($value)
 * @method static Builder<static>|QuizAttemptQuestion whereTimeSpentSeconds($value)
 * @method static Builder<static>|QuizAttemptQuestion whereUpdatedAt($value)
 * @method static Builder<static>|QuizAttemptQuestion whereWrongCount($value)
 *
 * @mixin \Eloquent
 */
class QuizAttemptQuestion extends Model
{
    protected $fillable = [
        'quiz_attempt_id', 'question_id', 'position', 'state',
        'selected_option', 'first_answered_at',
    ];

    protected function casts(): array
    {
        return [
            'state' => QuestionState::class,
            'selected_option' => OptionKey::class,
            'position' => 'integer',
            'wrong_count' => 'integer',
            'submission_count' => 'integer',
            'time_spent_seconds' => 'integer',
            'first_answered_at' => 'datetime',
            'mastered_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<QuizAttempt, $this> */
    public function attempt(): BelongsTo
    {
        return $this->belongsTo(QuizAttempt::class, 'quiz_attempt_id');
    }

    /** @return BelongsTo<Question, $this> */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    public function isMastered(): bool
    {
        return $this->state === QuestionState::Mastered;
    }

    public function needsRetry(): bool
    {
        return $this->state === QuestionState::RetryRequired;
    }

    public function scopeMastered(Builder $query): Builder
    {
        return $query->where('state', QuestionState::Mastered->value);
    }

    public function scopeOutstanding(Builder $query): Builder
    {
        return $query->whereIn('state', [
            QuestionState::Unanswered->value,
            QuestionState::RetryRequired->value,
        ]);
    }
}

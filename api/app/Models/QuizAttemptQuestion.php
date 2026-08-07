<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OptionKey;
use App\Enums\QuestionState;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-question state inside an attempt.
 *
 * `submission_count` doubles as the per-exposure option-shuffle seed input, which
 * is how docs/adr/003 is implemented with no additional storage.
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

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(QuizAttempt::class, 'quiz_attempt_id');
    }

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

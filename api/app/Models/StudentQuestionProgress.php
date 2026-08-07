<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OptionKey;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lifetime mastery per student per question, accumulating across cycles.
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

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

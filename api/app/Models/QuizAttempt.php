<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AttemptStatus;
use App\Enums\QuestionState;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuizAttempt extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'user_id', 'level_enrollment_id', 'level_id', 'daily_quiz_id',
        'day_number', 'cycle_number', 'attempt_number', 'status', 'required_count',
        'current_position', 'started_at', 'last_activity_at', 'device_type', 'device_hash',
    ];

    protected function casts(): array
    {
        return [
            'status' => AttemptStatus::class,
            'day_number' => 'integer',
            'cycle_number' => 'integer',
            'attempt_number' => 'integer',
            'required_count' => 'integer',
            'mastered_count' => 'integer',
            'correct_submissions' => 'integer',
            'wrong_submissions' => 'integer',
            'retry_count' => 'integer',
            'score' => 'integer',
            'current_position' => 'integer',
            'time_spent_seconds' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'last_activity_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function levelEnrollment(): BelongsTo
    {
        return $this->belongsTo(LevelEnrollment::class);
    }

    public function dailyQuiz(): BelongsTo
    {
        return $this->belongsTo(DailyQuiz::class);
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(Level::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(QuizAttemptQuestion::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(QuizAttemptAnswer::class);
    }

    // -------------------------------------------------------------- helpers --

    public function isInProgress(): bool
    {
        return $this->status === AttemptStatus::InProgress;
    }

    public function isCompleted(): bool
    {
        return $this->status === AttemptStatus::Completed;
    }

    /**
     * Authoritative 10/10 check, re-derived from the per-question state table
     * rather than trusting the cached counter or anything from the client.
     */
    public function masteredQuestionCount(): int
    {
        return $this->questions()
            ->where('state', QuestionState::Mastered->value)
            ->count();
    }

    public function isFullyMastered(): bool
    {
        return $this->masteredQuestionCount() >= $this->required_count;
    }

    public function scopeInProgress(Builder $query): Builder
    {
        return $query->where('status', AttemptStatus::InProgress->value);
    }

    public function scopeCompletedFullScore(Builder $query): Builder
    {
        return $query->where('status', AttemptStatus::Completed->value)
            ->whereColumn('score', '>=', 'required_count');
    }
}

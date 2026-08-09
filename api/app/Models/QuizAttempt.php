<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AttemptStatus;
use App\Enums\QuestionState;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property int $level_enrollment_id
 * @property int $level_id
 * @property int $daily_quiz_id
 * @property int $day_number
 * @property int $cycle_number
 * @property int $attempt_number
 * @property AttemptStatus $status
 * @property int $required_count
 * @property int $mastered_count
 * @property int $correct_submissions
 * @property int $wrong_submissions
 * @property int $retry_count
 * @property int $score
 * @property int $current_position
 * @property int $time_spent_seconds
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $last_activity_at
 * @property string|null $device_type
 * @property string|null $device_hash
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, QuizAttemptAnswer> $answers
 * @property-read int|null $answers_count
 * @property-read DailyQuiz|null $dailyQuiz
 * @property-read Level|null $level
 * @property-read LevelEnrollment|null $levelEnrollment
 * @property-read Collection<int, QuizAttemptQuestion> $questions
 * @property-read int|null $questions_count
 * @property-read User|null $user
 *
 * @method static Builder<static>|QuizAttempt completedFullScore()
 * @method static \Database\Factories\QuizAttemptFactory factory($count = null, $state = [])
 * @method static Builder<static>|QuizAttempt inProgress()
 * @method static Builder<static>|QuizAttempt newModelQuery()
 * @method static Builder<static>|QuizAttempt newQuery()
 * @method static Builder<static>|QuizAttempt query()
 * @method static Builder<static>|QuizAttempt whereAttemptNumber($value)
 * @method static Builder<static>|QuizAttempt whereCompletedAt($value)
 * @method static Builder<static>|QuizAttempt whereCorrectSubmissions($value)
 * @method static Builder<static>|QuizAttempt whereCreatedAt($value)
 * @method static Builder<static>|QuizAttempt whereCurrentPosition($value)
 * @method static Builder<static>|QuizAttempt whereCycleNumber($value)
 * @method static Builder<static>|QuizAttempt whereDailyQuizId($value)
 * @method static Builder<static>|QuizAttempt whereDayNumber($value)
 * @method static Builder<static>|QuizAttempt whereDeviceHash($value)
 * @method static Builder<static>|QuizAttempt whereDeviceType($value)
 * @method static Builder<static>|QuizAttempt whereId($value)
 * @method static Builder<static>|QuizAttempt whereLastActivityAt($value)
 * @method static Builder<static>|QuizAttempt whereLevelEnrollmentId($value)
 * @method static Builder<static>|QuizAttempt whereLevelId($value)
 * @method static Builder<static>|QuizAttempt whereMasteredCount($value)
 * @method static Builder<static>|QuizAttempt whereRequiredCount($value)
 * @method static Builder<static>|QuizAttempt whereRetryCount($value)
 * @method static Builder<static>|QuizAttempt whereScore($value)
 * @method static Builder<static>|QuizAttempt whereStartedAt($value)
 * @method static Builder<static>|QuizAttempt whereStatus($value)
 * @method static Builder<static>|QuizAttempt whereTimeSpentSeconds($value)
 * @method static Builder<static>|QuizAttempt whereUpdatedAt($value)
 * @method static Builder<static>|QuizAttempt whereUserId($value)
 * @method static Builder<static>|QuizAttempt whereUuid($value)
 * @method static Builder<static>|QuizAttempt whereWrongSubmissions($value)
 *
 * @mixin \Eloquent
 */
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

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<LevelEnrollment, $this> */
    public function levelEnrollment(): BelongsTo
    {
        return $this->belongsTo(LevelEnrollment::class);
    }

    /** @return BelongsTo<DailyQuiz, $this> */
    public function dailyQuiz(): BelongsTo
    {
        return $this->belongsTo(DailyQuiz::class);
    }

    /** @return BelongsTo<Level, $this> */
    public function level(): BelongsTo
    {
        return $this->belongsTo(Level::class);
    }

    /** @return HasMany<QuizAttemptQuestion, $this> */
    public function questions(): HasMany
    {
        return $this->hasMany(QuizAttemptQuestion::class);
    }

    /** @return HasMany<QuizAttemptAnswer, $this> */
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

<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TestAttemptStatus;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property int $level_test_id
 * @property int $level_enrollment_id
 * @property int $level_id
 * @property int $cycle_number
 * @property int $attempt_number
 * @property TestAttemptStatus $status
 * @property array<array-key, mixed> $question_order
 * @property int $shuffle_seed
 * @property int $total_questions
 * @property int $current_position
 * @property int $answered_count
 * @property int $flagged_count
 * @property int|null $correct_count
 * @property int|null $incorrect_count
 * @property int|null $unanswered_count
 * @property numeric|null $score
 * @property numeric|null $percentage
 * @property bool|null $passed
 * @property int|null $time_limit_seconds
 * @property int $time_spent_seconds
 * @property Carbon|null $expires_at
 * @property Carbon|null $started_at
 * @property Carbon|null $paused_at
 * @property Carbon|null $submitted_at
 * @property Carbon|null $graded_at
 * @property Carbon|null $last_sync_at
 * @property Carbon|null $result_released_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, LevelTestAnswer> $answers
 * @property-read int|null $answers_count
 * @property-read Certificate|null $certificate
 * @property-read LevelEnrollment|null $levelEnrollment
 * @property-read LevelTest $levelTest
 * @property-read User|null $user
 *
 * @method static \Database\Factories\LevelTestAttemptFactory factory($count = null, $state = [])
 * @method static Builder<static>|LevelTestAttempt newModelQuery()
 * @method static Builder<static>|LevelTestAttempt newQuery()
 * @method static Builder<static>|LevelTestAttempt open()
 * @method static Builder<static>|LevelTestAttempt query()
 * @method static Builder<static>|LevelTestAttempt whereAnsweredCount($value)
 * @method static Builder<static>|LevelTestAttempt whereAttemptNumber($value)
 * @method static Builder<static>|LevelTestAttempt whereCorrectCount($value)
 * @method static Builder<static>|LevelTestAttempt whereCreatedAt($value)
 * @method static Builder<static>|LevelTestAttempt whereCurrentPosition($value)
 * @method static Builder<static>|LevelTestAttempt whereCycleNumber($value)
 * @method static Builder<static>|LevelTestAttempt whereExpiresAt($value)
 * @method static Builder<static>|LevelTestAttempt whereFlaggedCount($value)
 * @method static Builder<static>|LevelTestAttempt whereGradedAt($value)
 * @method static Builder<static>|LevelTestAttempt whereId($value)
 * @method static Builder<static>|LevelTestAttempt whereIncorrectCount($value)
 * @method static Builder<static>|LevelTestAttempt whereLastSyncAt($value)
 * @method static Builder<static>|LevelTestAttempt whereLevelEnrollmentId($value)
 * @method static Builder<static>|LevelTestAttempt whereLevelId($value)
 * @method static Builder<static>|LevelTestAttempt whereLevelTestId($value)
 * @method static Builder<static>|LevelTestAttempt wherePassed($value)
 * @method static Builder<static>|LevelTestAttempt wherePausedAt($value)
 * @method static Builder<static>|LevelTestAttempt wherePercentage($value)
 * @method static Builder<static>|LevelTestAttempt whereQuestionOrder($value)
 * @method static Builder<static>|LevelTestAttempt whereResultReleasedAt($value)
 * @method static Builder<static>|LevelTestAttempt whereScore($value)
 * @method static Builder<static>|LevelTestAttempt whereShuffleSeed($value)
 * @method static Builder<static>|LevelTestAttempt whereStartedAt($value)
 * @method static Builder<static>|LevelTestAttempt whereStatus($value)
 * @method static Builder<static>|LevelTestAttempt whereSubmittedAt($value)
 * @method static Builder<static>|LevelTestAttempt whereTimeLimitSeconds($value)
 * @method static Builder<static>|LevelTestAttempt whereTimeSpentSeconds($value)
 * @method static Builder<static>|LevelTestAttempt whereTotalQuestions($value)
 * @method static Builder<static>|LevelTestAttempt whereUnansweredCount($value)
 * @method static Builder<static>|LevelTestAttempt whereUpdatedAt($value)
 * @method static Builder<static>|LevelTestAttempt whereUserId($value)
 * @method static Builder<static>|LevelTestAttempt whereUuid($value)
 *
 * @mixin \Eloquent
 */
class LevelTestAttempt extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'user_id', 'level_test_id', 'level_enrollment_id', 'level_id',
        'cycle_number', 'attempt_number', 'status', 'question_order', 'shuffle_seed',
        'total_questions', 'time_limit_seconds', 'expires_at', 'started_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => TestAttemptStatus::class,
            'question_order' => 'array',
            'shuffle_seed' => 'integer',
            'total_questions' => 'integer',
            'current_position' => 'integer',
            'answered_count' => 'integer',
            'flagged_count' => 'integer',
            'correct_count' => 'integer',
            'incorrect_count' => 'integer',
            'unanswered_count' => 'integer',
            'score' => 'decimal:2',
            'percentage' => 'decimal:2',
            'passed' => 'boolean',
            'time_limit_seconds' => 'integer',
            'time_spent_seconds' => 'integer',
            'expires_at' => 'datetime',
            'started_at' => 'datetime',
            'paused_at' => 'datetime',
            'submitted_at' => 'datetime',
            'graded_at' => 'datetime',
            'last_sync_at' => 'datetime',
            'result_released_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<LevelTest, $this> */
    public function levelTest(): BelongsTo
    {
        return $this->belongsTo(LevelTest::class);
    }

    /** @return BelongsTo<LevelEnrollment, $this> */
    public function levelEnrollment(): BelongsTo
    {
        return $this->belongsTo(LevelEnrollment::class);
    }

    /** @return HasMany<LevelTestAnswer, $this> */
    public function answers(): HasMany
    {
        return $this->hasMany(LevelTestAnswer::class, 'level_test_attempt_id');
    }

    /** @return HasOne<Certificate, $this> */
    public function certificate(): HasOne
    {
        return $this->hasOne(Certificate::class, 'level_test_attempt_id');
    }

    // -------------------------------------------------------------- helpers --

    public function isOpen(): bool
    {
        return in_array($this->status, [
            TestAttemptStatus::InProgress,
            TestAttemptStatus::Paused,
        ], true);
    }

    /** Server-side deadline is authoritative; the client timer is cosmetic. */
    public function hasExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function resultsAreReleased(): bool
    {
        return $this->result_released_at !== null && $this->result_released_at->isPast();
    }

    /** @return array<int, int> the slice of question ids for a windowed fetch */
    public function questionWindow(int $position, int $limit): array
    {
        /** @var array<int, int> $order */
        $order = $this->question_order ?? [];

        return array_slice($order, max(0, $position - 1), $limit);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [
            TestAttemptStatus::InProgress->value,
            TestAttemptStatus::Paused->value,
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EnrollmentStatus;
use App\Models\Concerns\HasUuid;
use Database\Factories\LevelEnrollmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * One row per (student, level, cycle). A repeat cycle is simply a new row with a
 * new assignment_seed, which is why cycles cost almost no extra code.
 *
 * @property int $id
 * @property string $uuid
 * @property int $programme_enrollment_id
 * @property int $user_id
 * @property int $level_id
 * @property int $cycle_number
 * @property EnrollmentStatus $status
 * @property bool|null $is_active
 * @property int $assignment_seed
 * @property Carbon|null $questions_assigned_at
 * @property int $current_day
 * @property int $highest_unlocked_day
 * @property int $completed_days
 * @property int|null $last_completed_day
 * @property Carbon|null $last_completed_at
 * @property numeric $progress_percent
 * @property numeric|null $accuracy_percent
 * @property int $total_study_seconds
 * @property Carbon|null $test_unlocked_at
 * @property Carbon|null $test_passed_at
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, EnrollmentDayQuestion> $dayQuestions
 * @property-read int|null $day_questions_count
 * @property-read Level|null $level
 * @property-read ProgrammeEnrollment|null $programmeEnrollment
 * @property-read Collection<int, QuizAttempt> $quizAttempts
 * @property-read int|null $quiz_attempts_count
 * @property-read Collection<int, LevelTestAttempt> $testAttempts
 * @property-read int|null $test_attempts_count
 * @property-read User|null $user
 *
 * @method static Builder<static>|LevelEnrollment active()
 * @method static \Database\Factories\LevelEnrollmentFactory factory($count = null, $state = [])
 * @method static Builder<static>|LevelEnrollment newModelQuery()
 * @method static Builder<static>|LevelEnrollment newQuery()
 * @method static Builder<static>|LevelEnrollment onlyTrashed()
 * @method static Builder<static>|LevelEnrollment query()
 * @method static Builder<static>|LevelEnrollment whereAccuracyPercent($value)
 * @method static Builder<static>|LevelEnrollment whereAssignmentSeed($value)
 * @method static Builder<static>|LevelEnrollment whereCompletedAt($value)
 * @method static Builder<static>|LevelEnrollment whereCompletedDays($value)
 * @method static Builder<static>|LevelEnrollment whereCreatedAt($value)
 * @method static Builder<static>|LevelEnrollment whereCurrentDay($value)
 * @method static Builder<static>|LevelEnrollment whereCycleNumber($value)
 * @method static Builder<static>|LevelEnrollment whereDeletedAt($value)
 * @method static Builder<static>|LevelEnrollment whereHighestUnlockedDay($value)
 * @method static Builder<static>|LevelEnrollment whereId($value)
 * @method static Builder<static>|LevelEnrollment whereIsActive($value)
 * @method static Builder<static>|LevelEnrollment whereLastCompletedAt($value)
 * @method static Builder<static>|LevelEnrollment whereLastCompletedDay($value)
 * @method static Builder<static>|LevelEnrollment whereLevelId($value)
 * @method static Builder<static>|LevelEnrollment whereProgrammeEnrollmentId($value)
 * @method static Builder<static>|LevelEnrollment whereProgressPercent($value)
 * @method static Builder<static>|LevelEnrollment whereQuestionsAssignedAt($value)
 * @method static Builder<static>|LevelEnrollment whereStartedAt($value)
 * @method static Builder<static>|LevelEnrollment whereStatus($value)
 * @method static Builder<static>|LevelEnrollment whereTestPassedAt($value)
 * @method static Builder<static>|LevelEnrollment whereTestUnlockedAt($value)
 * @method static Builder<static>|LevelEnrollment whereTotalStudySeconds($value)
 * @method static Builder<static>|LevelEnrollment whereUpdatedAt($value)
 * @method static Builder<static>|LevelEnrollment whereUserId($value)
 * @method static Builder<static>|LevelEnrollment whereUuid($value)
 * @method static Builder<static>|LevelEnrollment withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|LevelEnrollment withoutTrashed()
 *
 * @mixin \Eloquent
 */
class LevelEnrollment extends Model
{
    /** @use HasFactory<LevelEnrollmentFactory> */
    use HasFactory, HasUuid, SoftDeletes;

    protected $fillable = [
        'programme_enrollment_id', 'user_id', 'level_id', 'cycle_number',
        'status', 'is_active', 'assignment_seed', 'started_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => EnrollmentStatus::class,
            'is_active' => 'boolean',
            'cycle_number' => 'integer',
            'assignment_seed' => 'integer',
            'current_day' => 'integer',
            'highest_unlocked_day' => 'integer',
            'completed_days' => 'integer',
            'last_completed_day' => 'integer',
            'progress_percent' => 'decimal:2',
            'accuracy_percent' => 'decimal:2',
            'questions_assigned_at' => 'datetime',
            'last_completed_at' => 'datetime',
            'test_unlocked_at' => 'datetime',
            'test_passed_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<ProgrammeEnrollment, $this> */
    public function programmeEnrollment(): BelongsTo
    {
        return $this->belongsTo(ProgrammeEnrollment::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Level, $this> */
    public function level(): BelongsTo
    {
        return $this->belongsTo(Level::class);
    }

    /** @return HasMany<EnrollmentDayQuestion, $this> */
    public function dayQuestions(): HasMany
    {
        return $this->hasMany(EnrollmentDayQuestion::class);
    }

    /** @return HasMany<QuizAttempt, $this> */
    public function quizAttempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }

    /** @return HasMany<LevelTestAttempt, $this> */
    public function testAttempts(): HasMany
    {
        return $this->hasMany(LevelTestAttempt::class);
    }

    public function questionsAreAssigned(): bool
    {
        return $this->questions_assigned_at !== null;
    }

    public function hasCompletedAllDays(): bool
    {
        return $this->completed_days >= $this->level->total_quiz_days;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', EnrollmentStatus::Active->value);
    }
}

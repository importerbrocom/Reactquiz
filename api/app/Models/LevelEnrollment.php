<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EnrollmentStatus;
use App\Models\Concerns\HasUuid;
use Database\Factories\LevelEnrollmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One row per (student, level, cycle). A repeat cycle is simply a new row with a
 * new assignment_seed, which is why cycles cost almost no extra code.
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

    public function programmeEnrollment(): BelongsTo
    {
        return $this->belongsTo(ProgrammeEnrollment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Level, $this> */
    public function level(): BelongsTo
    {
        return $this->belongsTo(Level::class);
    }

    public function dayQuestions(): HasMany
    {
        return $this->hasMany(EnrollmentDayQuestion::class);
    }

    public function quizAttempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }

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

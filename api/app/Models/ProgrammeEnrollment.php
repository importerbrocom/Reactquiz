<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EnrollmentStatus;
use App\Models\Concerns\HasUuid;
use Database\Factories\ProgrammeEnrollmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property int $programme_id
 * @property EnrollmentStatus $status
 * @property bool|null $is_active
 * @property int $current_cycle
 * @property int $current_level
 * @property int $levels_completed_this_cycle
 * @property int $total_levels_completed
 * @property int $total_questions_mastered
 * @property numeric $overall_progress_percent
 * @property Carbon $enrolled_at
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, LevelEnrollment> $levelEnrollments
 * @property-read int|null $level_enrollments_count
 * @property-read Programme|null $programme
 * @property-read User|null $user
 *
 * @method static Builder<static>|ProgrammeEnrollment active()
 * @method static \Database\Factories\ProgrammeEnrollmentFactory factory($count = null, $state = [])
 * @method static Builder<static>|ProgrammeEnrollment newModelQuery()
 * @method static Builder<static>|ProgrammeEnrollment newQuery()
 * @method static Builder<static>|ProgrammeEnrollment onlyTrashed()
 * @method static Builder<static>|ProgrammeEnrollment query()
 * @method static Builder<static>|ProgrammeEnrollment whereCompletedAt($value)
 * @method static Builder<static>|ProgrammeEnrollment whereCreatedAt($value)
 * @method static Builder<static>|ProgrammeEnrollment whereCurrentCycle($value)
 * @method static Builder<static>|ProgrammeEnrollment whereCurrentLevel($value)
 * @method static Builder<static>|ProgrammeEnrollment whereDeletedAt($value)
 * @method static Builder<static>|ProgrammeEnrollment whereEnrolledAt($value)
 * @method static Builder<static>|ProgrammeEnrollment whereId($value)
 * @method static Builder<static>|ProgrammeEnrollment whereIsActive($value)
 * @method static Builder<static>|ProgrammeEnrollment whereLevelsCompletedThisCycle($value)
 * @method static Builder<static>|ProgrammeEnrollment whereOverallProgressPercent($value)
 * @method static Builder<static>|ProgrammeEnrollment whereProgrammeId($value)
 * @method static Builder<static>|ProgrammeEnrollment whereStartedAt($value)
 * @method static Builder<static>|ProgrammeEnrollment whereStatus($value)
 * @method static Builder<static>|ProgrammeEnrollment whereTotalLevelsCompleted($value)
 * @method static Builder<static>|ProgrammeEnrollment whereTotalQuestionsMastered($value)
 * @method static Builder<static>|ProgrammeEnrollment whereUpdatedAt($value)
 * @method static Builder<static>|ProgrammeEnrollment whereUserId($value)
 * @method static Builder<static>|ProgrammeEnrollment whereUuid($value)
 * @method static Builder<static>|ProgrammeEnrollment withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|ProgrammeEnrollment withoutTrashed()
 *
 * @mixin \Eloquent
 */
class ProgrammeEnrollment extends Model
{
    /** @use HasFactory<ProgrammeEnrollmentFactory> */
    use HasFactory, HasUuid, SoftDeletes;

    protected $fillable = [
        'user_id', 'programme_id', 'status', 'is_active',
        'current_cycle', 'current_level', 'enrolled_at', 'started_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => EnrollmentStatus::class,
            'is_active' => 'boolean',
            'current_cycle' => 'integer',
            'current_level' => 'integer',
            'levels_completed_this_cycle' => 'integer',
            'total_levels_completed' => 'integer',
            'overall_progress_percent' => 'decimal:2',
            'enrolled_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Programme, $this> */
    public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class);
    }

    /** @return HasMany<LevelEnrollment, $this> */
    public function levelEnrollments(): HasMany
    {
        return $this->hasMany(LevelEnrollment::class);
    }

    public function isOnFinalLevelOfCycle(): bool
    {
        return $this->current_level >= $this->programme->total_levels;
    }

    public function hasCyclesRemaining(): bool
    {
        return $this->programme->repeatsIndefinitely()
            || $this->current_cycle < $this->programme->total_cycles;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', EnrollmentStatus::Active->value);
    }
}

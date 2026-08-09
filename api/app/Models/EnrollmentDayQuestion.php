<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The per-student question assignment. Written once, read by index thereafter.
 *
 * @property int $id
 * @property int $level_enrollment_id
 * @property int $user_id
 * @property int $level_id
 * @property int $day_number
 * @property int $question_id
 * @property int $position
 * @property Carbon $assigned_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read LevelEnrollment|null $levelEnrollment
 * @property-read Question|null $question
 *
 * @method static Builder<static>|EnrollmentDayQuestion forDay(int $enrollmentId, int $dayNumber)
 * @method static Builder<static>|EnrollmentDayQuestion newModelQuery()
 * @method static Builder<static>|EnrollmentDayQuestion newQuery()
 * @method static Builder<static>|EnrollmentDayQuestion query()
 * @method static Builder<static>|EnrollmentDayQuestion whereAssignedAt($value)
 * @method static Builder<static>|EnrollmentDayQuestion whereCreatedAt($value)
 * @method static Builder<static>|EnrollmentDayQuestion whereDayNumber($value)
 * @method static Builder<static>|EnrollmentDayQuestion whereId($value)
 * @method static Builder<static>|EnrollmentDayQuestion whereLevelEnrollmentId($value)
 * @method static Builder<static>|EnrollmentDayQuestion whereLevelId($value)
 * @method static Builder<static>|EnrollmentDayQuestion wherePosition($value)
 * @method static Builder<static>|EnrollmentDayQuestion whereQuestionId($value)
 * @method static Builder<static>|EnrollmentDayQuestion whereUpdatedAt($value)
 * @method static Builder<static>|EnrollmentDayQuestion whereUserId($value)
 *
 * @mixin \Eloquent
 */
class EnrollmentDayQuestion extends Model
{
    protected $fillable = [
        'level_enrollment_id', 'user_id', 'level_id',
        'day_number', 'question_id', 'position', 'assigned_at',
    ];

    protected function casts(): array
    {
        return [
            'day_number' => 'integer',
            'position' => 'integer',
            'assigned_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<LevelEnrollment, $this> */
    public function levelEnrollment(): BelongsTo
    {
        return $this->belongsTo(LevelEnrollment::class);
    }

    /** @return BelongsTo<Question, $this> */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    public function scopeForDay(Builder $query, int $enrollmentId, int $dayNumber): Builder
    {
        return $query->where('level_enrollment_id', $enrollmentId)
            ->where('day_number', $dayNumber)
            ->orderBy('position');
    }
}

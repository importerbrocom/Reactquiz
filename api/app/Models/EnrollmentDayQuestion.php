<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The per-student question assignment. Written once, read by index thereafter.
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

    public function levelEnrollment(): BelongsTo
    {
        return $this->belongsTo(LevelEnrollment::class);
    }

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

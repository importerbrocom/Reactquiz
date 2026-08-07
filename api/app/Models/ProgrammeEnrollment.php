<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EnrollmentStatus;
use App\Models\Concerns\HasUuid;
use Database\Factories\ProgrammeEnrollmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Programme, $this> */
    public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class);
    }

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

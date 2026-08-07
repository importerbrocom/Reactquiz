<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TestAttemptStatus;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function levelTest(): BelongsTo
    {
        return $this->belongsTo(LevelTest::class);
    }

    public function levelEnrollment(): BelongsTo
    {
        return $this->belongsTo(LevelEnrollment::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(LevelTestAnswer::class, 'level_test_attempt_id');
    }

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

<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ContentStatus;
use Database\Factories\LevelTestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LevelTest extends Model
{
    /** @use HasFactory<LevelTestFactory> */
    use HasFactory;

    protected $fillable = [
        'level_id', 'version', 'day_number', 'title', 'question_count', 'pass_percentage',
        'time_limit_minutes', 'attempt_limit', 'shuffle_questions', 'allow_pause', 'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => ContentStatus::class,
            'shuffle_questions' => 'boolean',
            'allow_pause' => 'boolean',
            'pass_percentage' => 'decimal:2',
            'attempt_limit' => 'integer',
            'question_count' => 'integer',
        ];
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(Level::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(LevelTestAttempt::class);
    }

    public function allowsUnlimitedAttempts(): bool
    {
        return $this->attempt_limit === 0;
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ContentStatus;
use Database\Factories\DailyQuizFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DailyQuiz extends Model
{
    /** @use HasFactory<DailyQuizFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'level_id', 'day_number', 'title', 'question_count', 'status', 'available_from',
    ];

    protected function casts(): array
    {
        return [
            'status' => ContentStatus::class,
            'available_from' => 'date',
            'day_number' => 'integer',
            'question_count' => 'integer',
        ];
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(Level::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', ContentStatus::Active->value);
    }
}

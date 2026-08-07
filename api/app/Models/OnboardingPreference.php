<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnboardingPreference extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'step', 'completed_steps', 'selected_exam_category_id',
        'selected_programme_id', 'reminder_time', 'notifications_opt_in',
        'language', 'timezone', 'study_goal', 'daily_goal_minutes', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'completed_steps' => 'array',
            'notifications_opt_in' => 'boolean',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function examCategory(): BelongsTo
    {
        return $this->belongsTo(ExamCategory::class, 'selected_exam_category_id');
    }

    public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class, 'selected_programme_id');
    }

    public function isComplete(): bool
    {
        return $this->completed_at !== null;
    }
}

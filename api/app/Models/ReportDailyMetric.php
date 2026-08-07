<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Read by the admin dashboard. Never aggregate the answer log at request time.
 */
class ReportDailyMetric extends Model
{
    protected $fillable = [
        'metric_date', 'level_id', 'programme_id', 'exam_category_id',
        'active_students', 'new_students', 'quizzes_completed', 'tests_completed',
        'answers_submitted', 'correct_answers', 'avg_score', 'avg_accuracy',
        'study_seconds', 'notifications_sent', 'notifications_failed',
    ];

    protected function casts(): array
    {
        return [
            'metric_date' => 'date',
            'avg_score' => 'decimal:2',
            'avg_accuracy' => 'decimal:2',
        ];
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(Level::class);
    }

    public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class);
    }
}

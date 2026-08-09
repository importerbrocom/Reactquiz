<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Read by the admin dashboard. Never aggregate the answer log at request time.
 *
 * @property int $id
 * @property Carbon $metric_date
 * @property int|null $level_id
 * @property int|null $programme_id
 * @property int|null $exam_category_id
 * @property int $active_students
 * @property int $new_students
 * @property int $quizzes_completed
 * @property int $tests_completed
 * @property int $answers_submitted
 * @property int $correct_answers
 * @property numeric|null $avg_score
 * @property numeric|null $avg_accuracy
 * @property int $study_seconds
 * @property int $notifications_sent
 * @property int $notifications_failed
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Level|null $level
 * @property-read Programme|null $programme
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReportDailyMetric newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReportDailyMetric newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReportDailyMetric query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReportDailyMetric whereActiveStudents($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReportDailyMetric whereAnswersSubmitted($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReportDailyMetric whereAvgAccuracy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReportDailyMetric whereAvgScore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReportDailyMetric whereCorrectAnswers($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReportDailyMetric whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReportDailyMetric whereExamCategoryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReportDailyMetric whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReportDailyMetric whereLevelId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReportDailyMetric whereMetricDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReportDailyMetric whereNewStudents($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReportDailyMetric whereNotificationsFailed($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReportDailyMetric whereNotificationsSent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReportDailyMetric whereProgrammeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReportDailyMetric whereQuizzesCompleted($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReportDailyMetric whereStudySeconds($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReportDailyMetric whereTestsCompleted($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReportDailyMetric whereUpdatedAt($value)
 *
 * @mixin \Eloquent
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

    /** @return BelongsTo<Level, $this> */
    public function level(): BelongsTo
    {
        return $this->belongsTo(Level::class);
    }

    /** @return BelongsTo<Programme, $this> */
    public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class);
    }
}

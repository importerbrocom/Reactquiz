<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $step
 * @property array<array-key, mixed>|null $completed_steps
 * @property int|null $selected_exam_category_id
 * @property int|null $selected_programme_id
 * @property string|null $reminder_time
 * @property bool $notifications_opt_in
 * @property string $language
 * @property string|null $timezone
 * @property string|null $study_goal
 * @property int|null $daily_goal_minutes
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ExamCategory|null $examCategory
 * @property-read Programme|null $programme
 * @property-read User|null $user
 *
 * @method static \Database\Factories\OnboardingPreferenceFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnboardingPreference newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnboardingPreference newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnboardingPreference query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnboardingPreference whereCompletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnboardingPreference whereCompletedSteps($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnboardingPreference whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnboardingPreference whereDailyGoalMinutes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnboardingPreference whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnboardingPreference whereLanguage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnboardingPreference whereNotificationsOptIn($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnboardingPreference whereReminderTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnboardingPreference whereSelectedExamCategoryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnboardingPreference whereSelectedProgrammeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnboardingPreference whereStep($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnboardingPreference whereStudyGoal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnboardingPreference whereTimezone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnboardingPreference whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnboardingPreference whereUserId($value)
 *
 * @mixin \Eloquent
 */
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

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<ExamCategory, $this> */
    public function examCategory(): BelongsTo
    {
        return $this->belongsTo(ExamCategory::class, 'selected_exam_category_id');
    }

    /** @return BelongsTo<Programme, $this> */
    public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class, 'selected_programme_id');
    }

    public function isComplete(): bool
    {
        return $this->completed_at !== null;
    }
}

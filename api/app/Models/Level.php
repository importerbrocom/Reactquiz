<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ContentStatus;
use App\Enums\RetryMode;
use App\Enums\SelectionMode;
use App\Enums\UnlockMode;
use Database\Factories\LevelFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * One month: 30 days x 10 questions + a month-end test. (Was "course".)
 *
 * @property int $id
 * @property int $programme_id
 * @property int $level_number
 * @property string $title
 * @property string $slug
 * @property string|null $description
 * @property string|null $thumbnail_path
 * @property int $total_quiz_days
 * @property int $daily_question_count
 * @property SelectionMode $selection_mode
 * @property bool $spread_topics_across_days
 * @property UnlockMode $unlock_mode
 * @property RetryMode $retry_mode
 * @property int $recall_check_count
 * @property bool $show_explanation_on_correct
 * @property int $test_day
 * @property int $test_question_count
 * @property numeric $pass_percentage
 * @property int|null $test_time_limit_minutes
 * @property int $test_attempt_limit
 * @property bool $shuffle_test_questions
 * @property bool $allow_test_pause
 * @property bool $release_results_immediately
 * @property bool $issue_certificate
 * @property bool $reminders_enabled
 * @property string $default_reminder_time
 * @property int $missed_quiz_reminder_hours
 * @property ContentStatus $status
 * @property int $content_version
 * @property-read int|null $questions_count
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, DailyQuiz> $dailyQuizzes
 * @property-read int|null $daily_quizzes_count
 * @property-read Collection<int, LevelEnrollment> $enrollments
 * @property-read int|null $enrollments_count
 * @property-read Programme|null $programme
 * @property-read Collection<int, Question> $questions
 * @property-read LevelTest|null $test
 * @property-read Collection<int, LevelTest> $tests
 * @property-read int|null $tests_count
 *
 * @method static Builder<static>|Level active()
 * @method static \Database\Factories\LevelFactory factory($count = null, $state = [])
 * @method static Builder<static>|Level newModelQuery()
 * @method static Builder<static>|Level newQuery()
 * @method static Builder<static>|Level onlyTrashed()
 * @method static Builder<static>|Level query()
 * @method static Builder<static>|Level whereAllowTestPause($value)
 * @method static Builder<static>|Level whereContentVersion($value)
 * @method static Builder<static>|Level whereCreatedAt($value)
 * @method static Builder<static>|Level whereCreatedBy($value)
 * @method static Builder<static>|Level whereDailyQuestionCount($value)
 * @method static Builder<static>|Level whereDefaultReminderTime($value)
 * @method static Builder<static>|Level whereDeletedAt($value)
 * @method static Builder<static>|Level whereDescription($value)
 * @method static Builder<static>|Level whereId($value)
 * @method static Builder<static>|Level whereIssueCertificate($value)
 * @method static Builder<static>|Level whereLevelNumber($value)
 * @method static Builder<static>|Level whereMissedQuizReminderHours($value)
 * @method static Builder<static>|Level wherePassPercentage($value)
 * @method static Builder<static>|Level whereProgrammeId($value)
 * @method static Builder<static>|Level whereQuestionsCount($value)
 * @method static Builder<static>|Level whereRecallCheckCount($value)
 * @method static Builder<static>|Level whereReleaseResultsImmediately($value)
 * @method static Builder<static>|Level whereRemindersEnabled($value)
 * @method static Builder<static>|Level whereRetryMode($value)
 * @method static Builder<static>|Level whereSelectionMode($value)
 * @method static Builder<static>|Level whereShowExplanationOnCorrect($value)
 * @method static Builder<static>|Level whereShuffleTestQuestions($value)
 * @method static Builder<static>|Level whereSlug($value)
 * @method static Builder<static>|Level whereSpreadTopicsAcrossDays($value)
 * @method static Builder<static>|Level whereStatus($value)
 * @method static Builder<static>|Level whereTestAttemptLimit($value)
 * @method static Builder<static>|Level whereTestDay($value)
 * @method static Builder<static>|Level whereTestQuestionCount($value)
 * @method static Builder<static>|Level whereTestTimeLimitMinutes($value)
 * @method static Builder<static>|Level whereThumbnailPath($value)
 * @method static Builder<static>|Level whereTitle($value)
 * @method static Builder<static>|Level whereTotalQuizDays($value)
 * @method static Builder<static>|Level whereUnlockMode($value)
 * @method static Builder<static>|Level whereUpdatedAt($value)
 * @method static Builder<static>|Level whereUpdatedBy($value)
 * @method static Builder<static>|Level withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|Level withoutTrashed()
 *
 * @mixin \Eloquent
 */
class Level extends Model
{
    /** @use HasFactory<LevelFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'programme_id', 'level_number', 'title', 'slug', 'description', 'thumbnail_path',
        'total_quiz_days', 'daily_question_count', 'selection_mode', 'spread_topics_across_days',
        'unlock_mode', 'retry_mode', 'recall_check_count', 'show_explanation_on_correct',
        'test_day', 'test_question_count', 'pass_percentage', 'test_time_limit_minutes',
        'test_attempt_limit', 'shuffle_test_questions', 'allow_test_pause',
        'release_results_immediately', 'issue_certificate',
        'reminders_enabled', 'default_reminder_time', 'missed_quiz_reminder_hours',
        'status', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => ContentStatus::class,
            'selection_mode' => SelectionMode::class,
            'unlock_mode' => UnlockMode::class,
            'retry_mode' => RetryMode::class,
            'spread_topics_across_days' => 'boolean',
            'show_explanation_on_correct' => 'boolean',
            'shuffle_test_questions' => 'boolean',
            'allow_test_pause' => 'boolean',
            'release_results_immediately' => 'boolean',
            'issue_certificate' => 'boolean',
            'reminders_enabled' => 'boolean',
            'pass_percentage' => 'decimal:2',
            'total_quiz_days' => 'integer',
            'daily_question_count' => 'integer',
            'test_attempt_limit' => 'integer',
            'content_version' => 'integer',
            'questions_count' => 'integer',
        ];
    }

    /** @return BelongsTo<Programme, $this> */
    public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class);
    }

    /** @return HasMany<Question, $this> */
    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }

    /** @return HasMany<DailyQuiz, $this> */
    public function dailyQuizzes(): HasMany
    {
        return $this->hasMany(DailyQuiz::class)->orderBy('day_number');
    }

    /** @return HasOne<LevelTest, $this> */
    public function test(): HasOne
    {
        return $this->hasOne(LevelTest::class)->latestOfMany('version');
    }

    /** @return HasMany<LevelTest, $this> */
    public function tests(): HasMany
    {
        return $this->hasMany(LevelTest::class);
    }

    /** @return HasMany<LevelEnrollment, $this> */
    public function enrollments(): HasMany
    {
        return $this->hasMany(LevelEnrollment::class);
    }

    // -------------------------------------------------------------- helpers --

    /** 30 x 10 = 300. Every student must complete all of them. */
    public function requiredQuestionCount(): int
    {
        return $this->total_quiz_days * $this->daily_question_count;
    }

    public function hasEnoughQuestions(): bool
    {
        return $this->questions_count >= $this->requiredQuestionCount();
    }

    public function isPublishable(): bool
    {
        return $this->hasEnoughQuestions() && $this->dailyQuizzes()->count() === $this->total_quiz_days;
    }

    public function allowsUnlimitedTestAttempts(): bool
    {
        return $this->test_attempt_limit === 0;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', ContentStatus::Active->value);
    }
}

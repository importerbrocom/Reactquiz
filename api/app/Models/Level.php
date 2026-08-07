<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ContentStatus;
use App\Enums\RetryMode;
use App\Enums\SelectionMode;
use App\Enums\UnlockMode;
use Database\Factories\LevelFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One month: 30 days x 10 questions + a month-end test. (Was "course".)
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

    public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }

    public function dailyQuizzes(): HasMany
    {
        return $this->hasMany(DailyQuiz::class)->orderBy('day_number');
    }

    public function test(): HasOne
    {
        return $this->hasOne(LevelTest::class)->latestOfMany('version');
    }

    public function tests(): HasMany
    {
        return $this->hasMany(LevelTest::class);
    }

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

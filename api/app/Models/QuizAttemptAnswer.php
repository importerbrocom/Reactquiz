<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OptionKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Append-only. Never updated, never deleted while the attempt exists.
 *
 * @property int $id
 * @property int $quiz_attempt_id
 * @property int $question_id
 * @property string $client_answer_uuid
 * @property OptionKey $selected_option
 * @property bool $is_correct
 * @property int $submission_number
 * @property int|null $time_spent_ms
 * @property Carbon $answered_at
 * @property Carbon $recorded_at
 * @property bool $was_offline
 * @property Carbon|null $created_at
 * @property-read QuizAttempt $attempt
 * @property-read Question|null $question
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuizAttemptAnswer newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuizAttemptAnswer newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuizAttemptAnswer query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuizAttemptAnswer whereAnsweredAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuizAttemptAnswer whereClientAnswerUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuizAttemptAnswer whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuizAttemptAnswer whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuizAttemptAnswer whereIsCorrect($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuizAttemptAnswer whereQuestionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuizAttemptAnswer whereQuizAttemptId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuizAttemptAnswer whereRecordedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuizAttemptAnswer whereSelectedOption($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuizAttemptAnswer whereSubmissionNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuizAttemptAnswer whereTimeSpentMs($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuizAttemptAnswer whereWasOffline($value)
 *
 * @mixin \Eloquent
 */
class QuizAttemptAnswer extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'quiz_attempt_id', 'question_id', 'client_answer_uuid', 'selected_option',
        'is_correct', 'submission_number', 'time_spent_ms', 'answered_at',
        'recorded_at', 'was_offline',
    ];

    protected function casts(): array
    {
        return [
            'selected_option' => OptionKey::class,
            'is_correct' => 'boolean',
            'was_offline' => 'boolean',
            'submission_number' => 'integer',
            'time_spent_ms' => 'integer',
            'answered_at' => 'datetime',
            'recorded_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<QuizAttempt, $this> */
    public function attempt(): BelongsTo
    {
        return $this->belongsTo(QuizAttempt::class, 'quiz_attempt_id');
    }

    /** @return BelongsTo<Question, $this> */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}

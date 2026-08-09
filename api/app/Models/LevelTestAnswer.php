<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OptionKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $level_test_attempt_id
 * @property int $question_id
 * @property OptionKey|null $selected_option
 * @property bool|null $is_correct
 * @property bool $is_flagged
 * @property numeric|null $marks_awarded
 * @property int|null $time_spent_ms
 * @property string|null $client_batch_uuid
 * @property Carbon|null $answered_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read LevelTestAttempt $attempt
 * @property-read Question|null $question
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LevelTestAnswer newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LevelTestAnswer newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LevelTestAnswer query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LevelTestAnswer whereAnsweredAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LevelTestAnswer whereClientBatchUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LevelTestAnswer whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LevelTestAnswer whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LevelTestAnswer whereIsCorrect($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LevelTestAnswer whereIsFlagged($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LevelTestAnswer whereLevelTestAttemptId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LevelTestAnswer whereMarksAwarded($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LevelTestAnswer whereQuestionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LevelTestAnswer whereSelectedOption($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LevelTestAnswer whereTimeSpentMs($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LevelTestAnswer whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class LevelTestAnswer extends Model
{
    protected $fillable = [
        'level_test_attempt_id', 'question_id', 'selected_option', 'is_flagged',
        'time_spent_ms', 'client_batch_uuid', 'answered_at',
    ];

    /** Correctness is not exposed until grading. */
    protected $hidden = ['is_correct', 'marks_awarded'];

    protected function casts(): array
    {
        return [
            'selected_option' => OptionKey::class,
            'is_correct' => 'boolean',
            'is_flagged' => 'boolean',
            'marks_awarded' => 'decimal:2',
            'time_spent_ms' => 'integer',
            'answered_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<LevelTestAttempt, $this> */
    public function attempt(): BelongsTo
    {
        return $this->belongsTo(LevelTestAttempt::class, 'level_test_attempt_id');
    }

    /** @return BelongsTo<Question, $this> */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}

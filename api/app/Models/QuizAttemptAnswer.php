<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OptionKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only. Never updated, never deleted while the attempt exists.
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

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(QuizAttempt::class, 'quiz_attempt_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}

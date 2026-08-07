<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OptionKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(LevelTestAttempt::class, 'level_test_attempt_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentStreak extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'programme_id', 'current_streak', 'longest_streak',
        'last_activity_date', 'freeze_count',
    ];

    protected function casts(): array
    {
        return [
            'last_activity_date' => 'date',
            'current_streak' => 'integer',
            'longest_streak' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class);
    }
}

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
 * @property int $programme_id
 * @property int $current_streak
 * @property int $longest_streak
 * @property Carbon|null $last_activity_date
 * @property int $freeze_count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Programme|null $programme
 * @property-read User|null $user
 *
 * @method static \Database\Factories\StudentStreakFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentStreak newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentStreak newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentStreak query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentStreak whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentStreak whereCurrentStreak($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentStreak whereFreezeCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentStreak whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentStreak whereLastActivityDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentStreak whereLongestStreak($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentStreak whereProgrammeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentStreak whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentStreak whereUserId($value)
 *
 * @mixin \Eloquent
 */
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

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Programme, $this> */
    public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class);
    }
}

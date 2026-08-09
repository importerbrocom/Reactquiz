<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property int|null $level_id
 * @property int|null $programme_id
 * @property int|null $level_test_attempt_id
 * @property string $serial
 * @property numeric $score
 * @property numeric $percentage
 * @property string $status
 * @property string|null $storage_path
 * @property Carbon|null $issued_at
 * @property Carbon|null $revoked_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read LevelTestAttempt|null $attempt
 * @property-read Level|null $level
 * @property-read User|null $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Certificate newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Certificate newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Certificate query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Certificate whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Certificate whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Certificate whereIssuedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Certificate whereLevelId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Certificate whereLevelTestAttemptId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Certificate wherePercentage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Certificate whereProgrammeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Certificate whereRevokedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Certificate whereScore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Certificate whereSerial($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Certificate whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Certificate whereStoragePath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Certificate whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Certificate whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Certificate whereUuid($value)
 *
 * @mixin \Eloquent
 */
class Certificate extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'user_id', 'level_id', 'programme_id', 'level_test_attempt_id',
        'serial', 'score', 'percentage', 'status', 'storage_path', 'issued_at',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'decimal:2',
            'percentage' => 'decimal:2',
            'issued_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Level, $this> */
    public function level(): BelongsTo
    {
        return $this->belongsTo(Level::class);
    }

    /** @return BelongsTo<LevelTestAttempt, $this> */
    public function attempt(): BelongsTo
    {
        return $this->belongsTo(LevelTestAttempt::class, 'level_test_attempt_id');
    }
}

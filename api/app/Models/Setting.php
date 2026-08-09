<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $group
 * @property string $key
 * @property array<array-key, mixed>|null $value
 * @property string $type
 * @property bool $is_public
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static Builder<static>|Setting newModelQuery()
 * @method static Builder<static>|Setting newQuery()
 * @method static Builder<static>|Setting public()
 * @method static Builder<static>|Setting query()
 * @method static Builder<static>|Setting whereCreatedAt($value)
 * @method static Builder<static>|Setting whereGroup($value)
 * @method static Builder<static>|Setting whereId($value)
 * @method static Builder<static>|Setting whereIsPublic($value)
 * @method static Builder<static>|Setting whereKey($value)
 * @method static Builder<static>|Setting whereType($value)
 * @method static Builder<static>|Setting whereUpdatedAt($value)
 * @method static Builder<static>|Setting whereUpdatedBy($value)
 * @method static Builder<static>|Setting whereValue($value)
 *
 * @mixin \Eloquent
 */
class Setting extends Model
{
    protected $fillable = ['group', 'key', 'value', 'type', 'is_public', 'updated_by'];

    protected function casts(): array
    {
        return [
            'value' => 'array',
            'is_public' => 'boolean',
        ];
    }

    public function scopePublic(Builder $query): Builder
    {
        return $query->where('is_public', true);
    }
}

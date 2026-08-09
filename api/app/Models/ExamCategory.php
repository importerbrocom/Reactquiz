<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ContentStatus;
use Database\Factories\ExamCategoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property string|null $description
 * @property string|null $icon_path
 * @property string|null $image_path
 * @property string|null $color_token
 * @property int $display_order
 * @property ContentStatus $status
 * @property-read int|null $programmes_count
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, Programme> $programmes
 *
 * @method static Builder<static>|ExamCategory active()
 * @method static \Database\Factories\ExamCategoryFactory factory($count = null, $state = [])
 * @method static Builder<static>|ExamCategory newModelQuery()
 * @method static Builder<static>|ExamCategory newQuery()
 * @method static Builder<static>|ExamCategory onlyTrashed()
 * @method static Builder<static>|ExamCategory ordered()
 * @method static Builder<static>|ExamCategory query()
 * @method static Builder<static>|ExamCategory whereColorToken($value)
 * @method static Builder<static>|ExamCategory whereCreatedAt($value)
 * @method static Builder<static>|ExamCategory whereCreatedBy($value)
 * @method static Builder<static>|ExamCategory whereDeletedAt($value)
 * @method static Builder<static>|ExamCategory whereDescription($value)
 * @method static Builder<static>|ExamCategory whereDisplayOrder($value)
 * @method static Builder<static>|ExamCategory whereIconPath($value)
 * @method static Builder<static>|ExamCategory whereId($value)
 * @method static Builder<static>|ExamCategory whereImagePath($value)
 * @method static Builder<static>|ExamCategory whereProgrammesCount($value)
 * @method static Builder<static>|ExamCategory whereSlug($value)
 * @method static Builder<static>|ExamCategory whereStatus($value)
 * @method static Builder<static>|ExamCategory whereTitle($value)
 * @method static Builder<static>|ExamCategory whereUpdatedAt($value)
 * @method static Builder<static>|ExamCategory whereUpdatedBy($value)
 * @method static Builder<static>|ExamCategory withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|ExamCategory withoutTrashed()
 *
 * @mixin \Eloquent
 */
class ExamCategory extends Model
{
    /** @use HasFactory<ExamCategoryFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title', 'slug', 'description', 'icon_path', 'image_path',
        'color_token', 'display_order', 'status', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return ['status' => ContentStatus::class, 'display_order' => 'integer'];
    }

    /** @return HasMany<Programme, $this> */
    public function programmes(): HasMany
    {
        return $this->hasMany(Programme::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', ContentStatus::Active->value);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('display_order')->orderBy('title');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}

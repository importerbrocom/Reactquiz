<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ContentStatus;
use Database\Factories\ExamCategoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

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

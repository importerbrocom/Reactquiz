<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Public-facing identifier for records that appear in URLs handed to students,
 * so sequential primary keys are never enumerable from the outside.
 */
trait HasUuid
{
    public static function bootHasUuid(): void
    {
        static::creating(function (self $model): void {
            if (blank($model->uuid)) {
                $model->uuid = (string) Str::uuid7();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function scopeWhereUuid(Builder $query, string $uuid): Builder
    {
        return $query->where($this->qualifyColumn('uuid'), $uuid);
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ContentStatus;
use App\Enums\CycleReshuffleScope;
use App\Enums\UnlockMode;
use Database\Factories\ProgrammeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A 6-level, 1,800-question curriculum. See docs/adr/002.
 */
class Programme extends Model
{
    /** @use HasFactory<ProgrammeFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'exam_category_id', 'title', 'slug', 'description', 'thumbnail_path', 'bank_label',
        'total_levels', 'total_cycles', 'cycle_reshuffle_scope', 'next_programme_id',
        'require_test_pass_to_advance', 'level_unlock_mode', 'status', 'display_order',
        'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => ContentStatus::class,
            'cycle_reshuffle_scope' => CycleReshuffleScope::class,
            'level_unlock_mode' => UnlockMode::class,
            'require_test_pass_to_advance' => 'boolean',
            'total_levels' => 'integer',
            'total_cycles' => 'integer',
        ];
    }

    public function examCategory(): BelongsTo
    {
        return $this->belongsTo(ExamCategory::class);
    }

    /** @return HasMany<Level, $this> */
    public function levels(): HasMany
    {
        return $this->hasMany(Level::class)->orderBy('level_number');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(ProgrammeEnrollment::class);
    }

    public function nextProgramme(): BelongsTo
    {
        return $this->belongsTo(self::class, 'next_programme_id');
    }

    /** Total questions the programme needs before every level can be published. */
    public function requiredQuestionCount(): int
    {
        return (int) $this->levels->sum(
            static fn (Level $level): int => $level->requiredQuestionCount(),
        );
    }

    public function repeatsIndefinitely(): bool
    {
        return $this->total_cycles === 0;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', ContentStatus::Active->value);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}

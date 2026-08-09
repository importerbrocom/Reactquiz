<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ContentStatus;
use App\Enums\CycleReshuffleScope;
use App\Enums\UnlockMode;
use Database\Factories\ProgrammeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A 6-level, 1,800-question curriculum. See docs/adr/002.
 *
 * @property int $id
 * @property int $exam_category_id
 * @property string $title
 * @property string $slug
 * @property string|null $description
 * @property string|null $thumbnail_path
 * @property string|null $bank_label
 * @property int $total_levels
 * @property int $total_cycles
 * @property CycleReshuffleScope $cycle_reshuffle_scope
 * @property int|null $next_programme_id
 * @property bool $require_test_pass_to_advance
 * @property UnlockMode $level_unlock_mode
 * @property ContentStatus $status
 * @property int $questions_count
 * @property-read int|null $enrollments_count
 * @property int $display_order
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, ProgrammeEnrollment> $enrollments
 * @property-read ExamCategory|null $examCategory
 * @property-read Collection<int, Level> $levels
 * @property-read int|null $levels_count
 * @property-read Programme|null $nextProgramme
 *
 * @method static Builder<static>|Programme active()
 * @method static \Database\Factories\ProgrammeFactory factory($count = null, $state = [])
 * @method static Builder<static>|Programme newModelQuery()
 * @method static Builder<static>|Programme newQuery()
 * @method static Builder<static>|Programme onlyTrashed()
 * @method static Builder<static>|Programme query()
 * @method static Builder<static>|Programme whereBankLabel($value)
 * @method static Builder<static>|Programme whereCreatedAt($value)
 * @method static Builder<static>|Programme whereCreatedBy($value)
 * @method static Builder<static>|Programme whereCycleReshuffleScope($value)
 * @method static Builder<static>|Programme whereDeletedAt($value)
 * @method static Builder<static>|Programme whereDescription($value)
 * @method static Builder<static>|Programme whereDisplayOrder($value)
 * @method static Builder<static>|Programme whereEnrollmentsCount($value)
 * @method static Builder<static>|Programme whereExamCategoryId($value)
 * @method static Builder<static>|Programme whereId($value)
 * @method static Builder<static>|Programme whereLevelUnlockMode($value)
 * @method static Builder<static>|Programme whereNextProgrammeId($value)
 * @method static Builder<static>|Programme whereQuestionsCount($value)
 * @method static Builder<static>|Programme whereRequireTestPassToAdvance($value)
 * @method static Builder<static>|Programme whereSlug($value)
 * @method static Builder<static>|Programme whereStatus($value)
 * @method static Builder<static>|Programme whereThumbnailPath($value)
 * @method static Builder<static>|Programme whereTitle($value)
 * @method static Builder<static>|Programme whereTotalCycles($value)
 * @method static Builder<static>|Programme whereTotalLevels($value)
 * @method static Builder<static>|Programme whereUpdatedAt($value)
 * @method static Builder<static>|Programme whereUpdatedBy($value)
 * @method static Builder<static>|Programme withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|Programme withoutTrashed()
 *
 * @mixin \Eloquent
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

    /** @return BelongsTo<ExamCategory, $this> */
    public function examCategory(): BelongsTo
    {
        return $this->belongsTo(ExamCategory::class);
    }

    /** @return HasMany<Level, $this> */
    public function levels(): HasMany
    {
        return $this->hasMany(Level::class)->orderBy('level_number');
    }

    /** @return HasMany<ProgrammeEnrollment, $this> */
    public function enrollments(): HasMany
    {
        return $this->hasMany(ProgrammeEnrollment::class);
    }

    /** @return BelongsTo<self, $this> */
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

<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ContentStatus;
use App\Enums\CycleReshuffleScope;
use App\Enums\UnlockMode;
use App\Models\ExamCategory;
use App\Models\Programme;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Programme> */
final class ProgrammeFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $title = fake()->unique()->words(3, true).' Programme';

        return [
            'exam_category_id' => ExamCategory::factory(),
            'title' => Str::title($title),
            'slug' => Str::slug($title),
            'description' => fake()->paragraph(),
            'total_levels' => 6,
            'total_cycles' => 2,
            'cycle_reshuffle_scope' => CycleReshuffleScope::WithinLevel,
            'require_test_pass_to_advance' => false,
            'level_unlock_mode' => UnlockMode::Immediate,
            'status' => ContentStatus::Active,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (): array => ['status' => ContentStatus::Draft]);
    }

    public function singleCycle(): static
    {
        return $this->state(fn (): array => ['total_cycles' => 1]);
    }
}

<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ContentStatus;
use App\Models\ExamCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<ExamCategory> */
final class ExamCategoryFactory extends Factory
{
    private static int $sequence = 0;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        self::$sequence++;
        $title = fake()->randomElement(['FMGE', 'AMC', 'NEET-PG', 'PLAB', 'USMLE Step 1'])
            .' '.self::$sequence;

        return [
            'title' => $title,
            'slug' => Str::slug($title),
            'description' => fake()->sentence(),
            'display_order' => fake()->numberBetween(0, 10),
            'status' => ContentStatus::Active,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['status' => ContentStatus::Inactive]);
    }
}

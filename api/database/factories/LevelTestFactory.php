<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ContentStatus;
use App\Models\Level;
use App\Models\LevelTest;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LevelTest> */
final class LevelTestFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'level_id' => Level::factory(),
            'version' => 1,
            'day_number' => 31,
            'question_count' => 300,
            'pass_percentage' => 50.00,
            'attempt_limit' => 0,
            'shuffle_questions' => true,
            'allow_pause' => false,
            'status' => ContentStatus::Active,
        ];
    }
}

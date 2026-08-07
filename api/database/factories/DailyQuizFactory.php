<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ContentStatus;
use App\Models\DailyQuiz;
use App\Models\Level;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DailyQuiz> */
final class DailyQuizFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'level_id' => Level::factory(),
            'day_number' => 1,
            'question_count' => 10,
            'status' => ContentStatus::Active,
        ];
    }
}

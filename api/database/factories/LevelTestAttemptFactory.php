<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TestAttemptStatus;
use App\Models\Level;
use App\Models\LevelEnrollment;
use App\Models\LevelTest;
use App\Models\LevelTestAttempt;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LevelTestAttempt> */
final class LevelTestAttemptFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'level_test_id' => LevelTest::factory(),
            'level_enrollment_id' => LevelEnrollment::factory(),
            'level_id' => Level::factory(),
            'cycle_number' => 1,
            'attempt_number' => 1,
            'status' => TestAttemptStatus::InProgress,
            'question_order' => [],
            'shuffle_seed' => random_int(1, 2_000_000_000),
            'total_questions' => 0,
            'current_position' => 1,
            'answered_count' => 0,
            'flagged_count' => 0,
            'time_spent_seconds' => 0,
            'started_at' => now(),
        ];
    }
}

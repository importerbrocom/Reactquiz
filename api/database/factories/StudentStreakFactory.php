<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Programme;
use App\Models\StudentStreak;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<StudentStreak> */
final class StudentStreakFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'programme_id' => Programme::factory(),
            'current_streak' => 0,
            'longest_streak' => 0,
            'last_activity_date' => null,
            'freeze_count' => 0,
        ];
    }
}

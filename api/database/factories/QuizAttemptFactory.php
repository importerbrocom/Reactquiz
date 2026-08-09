<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AttemptStatus;
use App\Models\DailyQuiz;
use App\Models\Level;
use App\Models\LevelEnrollment;
use App\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<QuizAttempt> */
final class QuizAttemptFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'level_enrollment_id' => LevelEnrollment::factory(),
            'level_id' => Level::factory(),
            'daily_quiz_id' => DailyQuiz::factory(),
            'day_number' => 1,
            'cycle_number' => 1,
            'attempt_number' => 1,
            'status' => AttemptStatus::InProgress,
            'required_count' => 10,
            'mastered_count' => 0,
            'correct_submissions' => 0,
            'wrong_submissions' => 0,
            'retry_count' => 0,
            'score' => 0,
            'current_position' => 1,
            'time_spent_seconds' => 0,
            'started_at' => now(),
            'last_activity_at' => now(),
            'device_type' => null,
            'device_hash' => null,
        ];
    }

    public function completed(int $score = 10): static
    {
        return $this->state(fn (): array => [
            'status' => AttemptStatus::Completed,
            'score' => $score,
            'mastered_count' => $score,
            'completed_at' => now(),
        ]);
    }
}

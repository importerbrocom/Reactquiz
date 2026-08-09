<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ContentStatus;
use App\Enums\RetryMode;
use App\Enums\SelectionMode;
use App\Models\Level;
use App\Models\Programme;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Level> */
final class LevelFactory extends Factory
{
    private static int $sequence = 0;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        // A counter rather than fake()->unique(): unique() exhausts its pool and
        // slows down badly when a scenario builds hundreds of records.
        $number = (self::$sequence++ % 6) + 1;

        return [
            'programme_id' => Programme::factory(),
            'level_number' => $number,
            'title' => "Level {$number}",
            'slug' => Str::slug('level-'.$number.'-'.self::$sequence.'-'.Str::random(6)),
            'total_quiz_days' => 30,
            'daily_question_count' => 10,
            'selection_mode' => SelectionMode::ExhaustiveShuffle,
            'spread_topics_across_days' => true,
            'retry_mode' => RetryMode::RequeueAtEnd,
            'test_day' => 31,
            'test_question_count' => 300,
            'pass_percentage' => 50.00,
            'test_attempt_limit' => 0,
            'status' => ContentStatus::Active,
        ];
    }

    /** A deliberately tiny level, so tests can complete a whole month quickly. */
    public function tiny(int $days = 2, int $perDay = 3): static
    {
        return $this->state(fn (): array => [
            'total_quiz_days' => $days,
            'daily_question_count' => $perDay,
            'test_day' => $days + 1,
            'test_question_count' => $days * $perDay,
        ]);
    }

    public function immediateRetry(): static
    {
        return $this->state(fn (): array => ['retry_mode' => RetryMode::Immediate]);
    }
}

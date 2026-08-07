<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\EnrollmentStatus;
use App\Models\Level;
use App\Models\LevelEnrollment;
use App\Models\ProgrammeEnrollment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LevelEnrollment> */
final class LevelEnrollmentFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'programme_enrollment_id' => ProgrammeEnrollment::factory(),
            'user_id' => User::factory(),
            'level_id' => Level::factory(),
            'cycle_number' => 1,
            'status' => EnrollmentStatus::Active,
            'is_active' => true,
            'assignment_seed' => random_int(1, 2_000_000_000),
            'started_at' => now(),
        ];
    }
}

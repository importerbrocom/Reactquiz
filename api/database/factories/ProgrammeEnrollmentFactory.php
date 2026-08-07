<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\EnrollmentStatus;
use App\Models\Programme;
use App\Models\ProgrammeEnrollment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProgrammeEnrollment> */
final class ProgrammeEnrollmentFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'programme_id' => Programme::factory(),
            'status' => EnrollmentStatus::Active,
            'is_active' => true,
            'current_cycle' => 1,
            'current_level' => 1,
            'enrolled_at' => now(),
            'started_at' => now(),
        ];
    }
}

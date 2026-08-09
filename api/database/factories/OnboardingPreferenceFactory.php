<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\OnboardingPreference;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<OnboardingPreference> */
final class OnboardingPreferenceFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'step' => 'welcome',
            'completed_steps' => [],
            'language' => 'en',
            'timezone' => 'Asia/Kolkata',
            'notifications_opt_in' => false,
        ];
    }
}

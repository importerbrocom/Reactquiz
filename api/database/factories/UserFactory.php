<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** @extends Factory<User> */
final class UserFactory extends Factory
{
    protected static ?string $password = null;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            // Nullable columns are declared explicitly so factory-built models are
            // faithful to real rows. Without this, Model::preventAccessingMissing
            // (enabled outside production) throws when a Resource reads them.
            'phone' => null,
            'avatar_path' => null,
            'onboarding_completed_at' => null,
            'last_login_at' => null,
            'last_active_at' => null,
            'locked_until' => null,
            'sessions_valid_after' => null,
            'failed_login_attempts' => 0,
            'password' => self::$password ??= Hash::make('password'),
            'role' => UserRole::Student,
            'status' => UserStatus::Active,
            'timezone' => 'Asia/Kolkata',
            'locale' => 'en',
            'remember_token' => Str::random(10),
        ];
    }

    public function admin(): static
    {
        return $this->state(fn (): array => [
            'role' => UserRole::Admin,
            'status' => UserStatus::Active,
        ]);
    }

    public function unverified(): static
    {
        return $this->state(fn (): array => [
            'email_verified_at' => null,
            'status' => UserStatus::PendingVerification,
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn (): array => ['status' => UserStatus::Suspended]);
    }

    public function locked(): static
    {
        return $this->state(fn (): array => [
            'failed_login_attempts' => 5,
            'locked_until' => now()->addMinutes(10),
        ]);
    }
}

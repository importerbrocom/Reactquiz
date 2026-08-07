<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<RefreshToken> */
final class RefreshTokenFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'family_id' => (string) Str::uuid7(),
            'token_hash' => hash('sha256', Str::random(64)),
            'device_name' => 'Test device',
            'expires_at' => now()->addDays(30),
        ];
    }
}

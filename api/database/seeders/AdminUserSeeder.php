<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

final class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = (string) config('quiz.seed.admin_email');

        $admin = User::query()->firstOrNew(['email' => $email]);

        $admin->fill([
            'name' => (string) config('quiz.seed.admin_name'),
            'timezone' => 'Asia/Kolkata',
        ]);

        $admin->forceFill([
            'password' => Hash::make((string) config('quiz.seed.admin_password')),
            'role' => UserRole::Admin,
            'status' => UserStatus::Active,
            'email_verified_at' => now(),
        ])->save();

        $admin->syncRoles([UserRole::Admin->value]);

        $this->command?->warn("  Admin: {$email} (change the seeded password immediately)");
    }
}

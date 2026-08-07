<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

final class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            ['app', 'support_email', 'support@example.com', 'string', true],
            ['app', 'maintenance_notice', null, 'string', true],
            ['quiz', 'show_streak_widget', true, 'boolean', true],
            ['quiz', 'allow_practice_mode', true, 'boolean', true],
            ['push', 'enabled', true, 'boolean', true],
            ['push', 'quiet_hours_start', '22:00', 'string', false],
            ['push', 'quiet_hours_end', '07:00', 'string', false],
            ['push', 'max_per_user_per_day', 3, 'integer', false],
            ['security', 'require_verified_email', true, 'boolean', false],
        ];

        foreach ($settings as [$group, $key, $value, $type, $isPublic]) {
            Setting::query()->updateOrCreate(
                ['group' => $group, 'key' => $key],
                ['value' => $value, 'type' => $type, 'is_public' => $isPublic],
            );
        }
    }
}

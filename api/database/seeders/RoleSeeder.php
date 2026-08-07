<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

final class RoleSeeder extends Seeder
{
    /**
     * Permissions are fine-grained so a future "content editor" role can exist
     * without a schema change; roles are what the middleware checks.
     */
    private const PERMISSIONS = [
        'categories.view', 'categories.manage',
        'programmes.view', 'programmes.manage',
        'levels.view', 'levels.manage', 'levels.publish',
        'questions.view', 'questions.manage', 'questions.import', 'questions.export',
        'students.view', 'students.manage', 'students.impersonate',
        'enrolments.view', 'enrolments.manage',
        'attempts.view', 'attempts.override',
        'notifications.send',
        'reports.view', 'reports.export',
        'settings.manage',
        'activity.view',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $admin = Role::findOrCreate(UserRole::Admin->value, 'web');
        $admin->syncPermissions(self::PERMISSIONS);

        // Students hold no admin permissions at all; their access is governed by
        // enrolment and the quiz policies, not by permission strings.
        Role::findOrCreate(UserRole::Student->value, 'web');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}

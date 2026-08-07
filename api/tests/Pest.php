<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/*
 * Feature tests get a migrated database and the role/permission rows, which are
 * structural rather than fixture data. Unit tests get neither, so they stay fast
 * and cannot accidentally depend on the database.
 */
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(fn () => test()->seed(RoleSeeder::class))
    ->in('Feature');

pest()->extend(TestCase::class)->in('Unit');

/*
|--------------------------------------------------------------------------
| Shared helpers
|--------------------------------------------------------------------------
*/

function student(array $attributes = []): User
{
    $user = User::factory()->create($attributes);
    $user->assignRole(UserRole::Student->value);

    return $user;
}

function admin(array $attributes = []): User
{
    $user = User::factory()->admin()->create($attributes);
    $user->assignRole(UserRole::Admin->value);

    return $user;
}

/**
 * Authenticate with REAL token abilities.
 *
 * $this->actingAs($user, 'sanctum') would mint a TransientToken that grants every
 * ability, which would silently hide a broken `abilities:` middleware. Using
 * Sanctum::actingAs with an explicit ability list keeps that gate under test.
 */
function actingAsStudent(?User $user = null): User
{
    $user ??= student();
    Sanctum::actingAs($user, [UserRole::Student->value]);

    return $user;
}

function actingAsAdmin(?User $user = null): User
{
    $user ??= admin();
    Sanctum::actingAs($user, [UserRole::Admin->value]);

    return $user;
}

/** Asserts the standard API envelope shape. */
expect()->extend('toBeApiEnvelope', function (bool $success = true) {
    expect($this->value)
        ->toHaveKeys(['success', 'message', 'data', 'errors'])
        ->and($this->value['success'])->toBe($success);

    return $this;
});

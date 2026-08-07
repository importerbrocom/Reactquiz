<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

it('lets an admin read the admin counters', function (): void {
    actingAsAdmin();

    $this->getJson('/api/v1/admin/counters')
        ->assertOk()
        ->assertJsonStructure(['data' => ['students', 'programmes', 'levels', 'questions']]);
});

it('refuses a student on admin routes', function (): void {
    actingAsStudent();

    $this->getJson('/api/v1/admin/counters')->assertStatus(403);
});

it('refuses an unauthenticated request on admin routes', function (): void {
    $this->getJson('/api/v1/admin/counters')
        ->assertStatus(401)
        ->assertJsonPath('errors.code', 'UNAUTHENTICATED');
});

it('refuses a token that lacks the admin ability even if the role column says admin', function (): void {
    // Belt and braces: the token was minted for a student session, so even an
    // account that is now an admin cannot use it on admin routes.
    $user = admin();
    Sanctum::actingAs($user, [UserRole::Student->value]);

    $this->getJson('/api/v1/admin/counters')->assertStatus(403);
});

it('refuses an admin token when the database role has been downgraded', function (): void {
    // The reverse: the token still claims admin, but the role column is the
    // authority, so revoking a role takes effect on the very next request.
    $user = admin();
    Sanctum::actingAs($user, [UserRole::Admin->value]);

    $user->forceFill(['role' => UserRole::Student])->save();

    $this->getJson('/api/v1/admin/counters')
        ->assertStatus(403)
        ->assertJsonPath('errors.code', 'FORBIDDEN_ROLE');
});

it('refuses a suspended account even with a valid token', function (): void {
    $user = admin();
    Sanctum::actingAs($user, [UserRole::Admin->value]);
    $user->forceFill(['status' => UserStatus::Suspended])->save();

    $this->getJson('/api/v1/admin/counters')
        ->assertStatus(403)
        ->assertJsonPath('errors.code', 'ACCOUNT_SUSPENDED');
});

it('refuses tokens minted before a global session invalidation', function (): void {
    $user = User::factory()->admin()->create(['password' => Hash::make('correct-horse-42')]);
    $user->assignRole(UserRole::Admin->value);

    $session = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email, 'password' => 'correct-horse-42', 'mobile' => true,
    ])->json('data');

    $this->withHeader('Authorization', 'Bearer '.$session['access_token'])
        ->getJson('/api/v1/admin/counters')
        ->assertOk();

    // Simulates "log out all devices" happening on another device.
    $this->travel(1)->second();
    $user->forceFill(['sessions_valid_after' => now()])->save();

    // Laravel memoises the resolved guard user for the lifetime of the test
    // application, so without this the next request would reuse the User instance
    // loaded a moment ago and miss the new sessions_valid_after value. In
    // production every request is a fresh process; this just reproduces that.
    $this->app['auth']->forgetGuards();

    $this->withHeader('Authorization', 'Bearer '.$session['access_token'])
        ->getJson('/api/v1/admin/counters')
        ->assertStatus(401)
        ->assertJsonPath('errors.code', 'SESSION_REVOKED');
});

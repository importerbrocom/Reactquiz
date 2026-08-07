<?php

declare(strict_types=1);

use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('returns the authenticated user with onboarding and enrolment context', function (): void {
    $user = actingAsStudent();

    $this->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.user.id', $user->uuid)
        ->assertJsonPath('data.user.role', 'student')
        ->assertJsonStructure(['data' => ['user', 'permissions', 'onboarding', 'active_enrolment']]);
});

it('never exposes the password hash', function (): void {
    actingAsStudent();

    $body = $this->getJson('/api/v1/auth/me')->assertOk()->getContent();

    expect($body)->not->toContain('password')
        ->and($body)->not->toContain('$2y$');
});

it('requires authentication for protected endpoints', function (string $method, string $uri): void {
    $this->json($method, $uri)
        ->assertStatus(401)
        ->assertJsonPath('errors.code', 'UNAUTHENTICATED');
})->with([
    ['GET', '/api/v1/auth/me'],
    ['PATCH', '/api/v1/auth/me'],
    ['POST', '/api/v1/auth/logout'],
    ['GET', '/api/v1/auth/devices'],
]);

it('updates the profile but ignores fields the client may not set', function (): void {
    $user = actingAsStudent();

    $this->patchJson('/api/v1/auth/me', [
        'name' => 'Updated Name',
        'timezone' => 'Asia/Tashkent',
        'role' => 'admin',          // must be ignored
        'email' => 'hijack@example.com',
    ])->assertOk()->assertJsonPath('data.name', 'Updated Name');

    $user->refresh();

    expect($user->timezone)->toBe('Asia/Tashkent')
        ->and($user->role->value)->toBe('student')
        ->and($user->email)->not->toBe('hijack@example.com');
});

it('logs out the current device only', function (): void {
    $user = User::factory()->create(['password' => Hash::make('correct-horse-42')]);

    $first = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email, 'password' => 'correct-horse-42', 'mobile' => true,
    ])->json('data');

    $this->postJson('/api/v1/auth/login', [
        'email' => $user->email, 'password' => 'correct-horse-42', 'mobile' => true,
    ])->assertOk();

    expect($user->tokens()->count())->toBe(2);

    $this->withHeader('Authorization', 'Bearer '.$first['access_token'])
        ->withHeader('X-Refresh-Token', $first['refresh_token'])
        ->postJson('/api/v1/auth/logout')
        ->assertOk();

    // The other device survives.
    expect($user->tokens()->count())->toBe(1);
});

it('logs out every device and invalidates earlier tokens globally', function (): void {
    $user = User::factory()->create(['password' => Hash::make('correct-horse-42')]);

    $session = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email, 'password' => 'correct-horse-42', 'mobile' => true,
    ])->json('data');

    $this->postJson('/api/v1/auth/login', [
        'email' => $user->email, 'password' => 'correct-horse-42', 'mobile' => true,
    ]);

    $this->withHeader('Authorization', 'Bearer '.$session['access_token'])
        ->postJson('/api/v1/auth/logout-all', ['password' => 'correct-horse-42'])
        ->assertOk();

    $user->refresh();

    expect($user->tokens()->count())->toBe(0)
        ->and(RefreshToken::query()->where('user_id', $user->id)->usable()->count())->toBe(0)
        ->and($user->sessions_valid_after)->not->toBeNull();
});

it('requires the correct password to log out everywhere', function (): void {
    $user = actingAsStudent();

    $this->postJson('/api/v1/auth/logout-all', ['password' => 'not-my-password'])
        ->assertStatus(422);

    expect($user->refresh()->sessions_valid_after)->toBeNull();
});

it('lists active devices and can revoke one', function (): void {
    $user = User::factory()->create(['password' => Hash::make('correct-horse-42')]);

    $this->postJson('/api/v1/auth/login', [
        'email' => $user->email, 'password' => 'correct-horse-42', 'mobile' => true,
    ]);
    $second = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email, 'password' => 'correct-horse-42', 'mobile' => true,
    ])->json('data');

    $devices = $this->withHeader('Authorization', 'Bearer '.$second['access_token'])
        ->getJson('/api/v1/auth/devices')
        ->assertOk()
        ->json('data');

    expect($devices)->toHaveCount(2);

    $this->withHeader('Authorization', 'Bearer '.$second['access_token'])
        ->deleteJson('/api/v1/auth/devices/'.$devices[0]['id'])
        ->assertOk();

    expect(RefreshToken::query()->where('user_id', $user->id)->usable()->count())->toBe(1);
});

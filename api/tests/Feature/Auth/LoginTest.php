<?php

declare(strict_types=1);

use App\Enums\UserStatus;
use App\Models\LoginAttempt;
use App\Models\User;
use Illuminate\Cache\RateLimiter;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Hash;

beforeEach(function (): void {
    $this->user = User::factory()->create([
        'email' => 'student@example.com',
        'password' => Hash::make('correct-horse-42'),
    ]);
});

it('signs in with valid credentials', function (): void {
    $this->postJson('/api/v1/auth/login', [
        'email' => 'student@example.com',
        'password' => 'correct-horse-42',
    ])->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['data' => ['user', 'access_token', 'expires_in']]);

    expect($this->user->refresh()->last_login_at)->not->toBeNull()
        ->and($this->user->failed_login_attempts)->toBe(0);
});

it('marks auth responses as no-store so nothing caches a token', function (): void {
    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'student@example.com',
        'password' => 'correct-horse-42',
    ])->assertOk();

    expect($response->headers->get('Cache-Control'))->toContain('no-store');
});

it('rejects a wrong password without revealing anything', function (): void {
    $this->postJson('/api/v1/auth/login', [
        'email' => 'student@example.com',
        'password' => 'wrong-password',
    ])->assertStatus(401)
        ->assertJsonPath('errors.code', 'INVALID_CREDENTIALS');

    expect($this->user->refresh()->failed_login_attempts)->toBe(1);
});

it('gives an identical response whether or not the account exists', function (): void {
    $known = $this->postJson('/api/v1/auth/login', [
        'email' => 'student@example.com',
        'password' => 'wrong-password',
    ]);

    $unknown = $this->postJson('/api/v1/auth/login', [
        'email' => 'nobody@example.com',
        'password' => 'wrong-password',
    ]);

    // Same status, same code, same message: no enumeration oracle.
    expect($unknown->status())->toBe($known->status())
        ->and($unknown->json('message'))->toBe($known->json('message'))
        ->and($unknown->json('errors.code'))->toBe($known->json('errors.code'));
});

it('locks the account progressively after repeated failures', function (): void {
    foreach (range(1, 5) as $ignored) {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'student@example.com',
            'password' => 'wrong-password',
        ]);
    }

    $this->user->refresh();

    expect($this->user->failed_login_attempts)->toBe(5)
        ->and($this->user->isLocked())->toBeTrue();

    // Clear the per-minute throttle so this asserts the LOCKOUT specifically,
    // rather than the rate limiter that would also have returned 429.
    Illuminate\Support\Facades\RateLimiter::clear(
        sha1('student@example.com|127.0.0.1'),
    );
    $this->app->make(RateLimiter::class)->clear('auth-login');

    // Even the correct password is refused while the account is locked.
    $this->withoutMiddleware(ThrottleRequests::class)
        ->postJson('/api/v1/auth/login', [
            'email' => 'student@example.com',
            'password' => 'correct-horse-42',
        ])->assertStatus(423)->assertJsonPath('errors.code', 'ACCOUNT_LOCKED');
});

it('records attempts with a hashed email rather than plaintext', function (): void {
    $this->postJson('/api/v1/auth/login', [
        'email' => 'ghost@example.com',
        'password' => 'wrong-password',
    ]);

    $attempt = LoginAttempt::query()->latest('id')->firstOrFail();

    expect($attempt->email_hash)->toBe(LoginAttempt::hashEmail('ghost@example.com'))
        ->and($attempt->email_hash)->not->toContain('ghost')
        ->and($attempt->successful)->toBeFalse();
});

it('refuses suspended accounts', function (): void {
    $this->user->forceFill(['status' => UserStatus::Suspended])->save();

    $this->postJson('/api/v1/auth/login', [
        'email' => 'student@example.com',
        'password' => 'correct-horse-42',
    ])->assertStatus(403)->assertJsonPath('errors.code', 'ACCOUNT_SUSPENDED');
});

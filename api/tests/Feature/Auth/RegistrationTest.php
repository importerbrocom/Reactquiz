<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\OnboardingPreference;
use App\Models\User;

it('registers a student and returns an access token', function (): void {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Arun Nair',
        'email' => 'arun@example.com',
        'password' => 'correct-horse-42',
        'password_confirmation' => 'correct-horse-42',
        'timezone' => 'Asia/Kolkata',
    ]);

    $response->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['success', 'message', 'data' => ['user', 'access_token', 'expires_in']]);

    $user = User::query()->where('email', 'arun@example.com')->firstOrFail();

    expect($user->role)->toBe(UserRole::Student)
        ->and($user->status)->toBe(UserStatus::PendingVerification)
        ->and($user->hasRole(UserRole::Student->value))->toBeTrue();
});

it('creates an onboarding record so progress can be resumed', function (): void {
    $this->postJson('/api/v1/auth/register', [
        'name' => 'Arun Nair',
        'email' => 'arun@example.com',
        'password' => 'correct-horse-42',
        'password_confirmation' => 'correct-horse-42',
    ])->assertCreated();

    $user = User::query()->where('email', 'arun@example.com')->firstOrFail();

    expect(OnboardingPreference::query()->where('user_id', $user->id)->first())
        ->not->toBeNull()
        ->step->toBe('welcome');
});

it('never lets the client choose its own role', function (): void {
    $this->postJson('/api/v1/auth/register', [
        'name' => 'Escalation Attempt',
        'email' => 'sneaky@example.com',
        'password' => 'correct-horse-42',
        'password_confirmation' => 'correct-horse-42',
        'role' => 'admin',
        'status' => 'active',
    ])->assertCreated();

    $user = User::query()->where('email', 'sneaky@example.com')->firstOrFail();

    expect($user->role)->toBe(UserRole::Student)
        ->and($user->status)->toBe(UserStatus::PendingVerification);
});

it('sets the refresh token as an http-only cookie for web clients', function (): void {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Arun Nair',
        'email' => 'arun@example.com',
        'password' => 'correct-horse-42',
        'password_confirmation' => 'correct-horse-42',
    ])->assertCreated();

    $cookie = collect($response->headers->getCookies())
        ->firstWhere(fn ($c) => $c->getName() === config('security.refresh_cookie.name'));

    expect($cookie)->not->toBeNull()
        ->and($cookie->isHttpOnly())->toBeTrue()
        ->and($cookie->getSameSite())->toBe('strict')
        ->and($cookie->getPath())->toBe('/api/v1/auth');

    // The refresh token must NOT also be in the body for a web client.
    $response->assertJsonMissingPath('data.refresh_token');
});

it('returns the refresh token in the body for mobile clients', function (): void {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Arun Nair',
        'email' => 'arun@example.com',
        'password' => 'correct-horse-42',
        'password_confirmation' => 'correct-horse-42',
        'mobile' => true,
    ])->assertCreated();

    expect($response->json('data.refresh_token'))->toBeString()->not->toBeEmpty();
});

it('rejects weak, duplicate and malformed registrations', function (array $payload, string $field): void {
    User::factory()->create(['email' => 'taken@example.com']);

    $this->postJson('/api/v1/auth/register', $payload)
        ->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonPath('errors.'.$field.'.0', fn ($m) => is_string($m));
})->with([
    'short password' => [[
        'name' => 'A B', 'email' => 'a@example.com',
        'password' => 'short', 'password_confirmation' => 'short',
    ], 'password'],
    'unconfirmed password' => [[
        'name' => 'A B', 'email' => 'b@example.com',
        'password' => 'correct-horse-42', 'password_confirmation' => 'different-99',
    ], 'password'],
    'duplicate email' => [[
        'name' => 'A B', 'email' => 'taken@example.com',
        'password' => 'correct-horse-42', 'password_confirmation' => 'correct-horse-42',
    ], 'email'],
    'missing name' => [[
        'email' => 'c@example.com',
        'password' => 'correct-horse-42', 'password_confirmation' => 'correct-horse-42',
    ], 'name'],
]);

<?php

declare(strict_types=1);

use App\Enums\UserStatus;
use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

function loginAndGetRefresh(): array
{
    $user = User::factory()->create([
        'email' => 'student@example.com',
        'password' => Hash::make('correct-horse-42'),
    ]);

    $response = test()->postJson('/api/v1/auth/login', [
        'email' => 'student@example.com',
        'password' => 'correct-horse-42',
        'mobile' => true,      // returns the refresh token in the body
    ])->assertOk();

    return [$user, $response->json('data.refresh_token'), $response->json('data.access_token')];
}

it('exchanges a refresh token for a new pair', function (): void {
    [$user, $refresh, $access] = loginAndGetRefresh();

    $response = $this->withHeader('X-Refresh-Token', $refresh)
        ->postJson('/api/v1/auth/refresh')
        ->assertOk();

    $newAccess = $response->json('data.access_token');
    $newRefresh = $response->json('data.refresh_token');

    expect($newAccess)->not->toBe($access)
        ->and($newRefresh)->not->toBe($refresh);

    // The old refresh token is marked rotated, not deleted, so reuse is detectable.
    expect(RefreshToken::query()->where('token_hash', hash('sha256', $refresh))->first()->rotated_at)
        ->not->toBeNull();
});

it('detects reuse of a rotated token and revokes the whole family', function (): void {
    [$user, $refresh] = loginAndGetRefresh();

    // First use: legitimate.
    $this->withHeader('X-Refresh-Token', $refresh)
        ->postJson('/api/v1/auth/refresh')
        ->assertOk();

    // Second use of the SAME token: this means it leaked.
    $this->withHeader('X-Refresh-Token', $refresh)
        ->postJson('/api/v1/auth/refresh')
        ->assertStatus(401)
        ->assertJsonPath('errors.code', 'SESSION_REVOKED');

    // Every token in the family is now dead, including the one issued to the
    // legitimate user — they must sign in again, which is the correct trade.
    expect(RefreshToken::query()->where('user_id', $user->id)->usable()->count())->toBe(0)
        ->and($user->tokens()->count())->toBe(0);
});

it('rejects an unknown refresh token', function (): void {
    $this->withHeader('X-Refresh-Token', str_repeat('x', 64))
        ->postJson('/api/v1/auth/refresh')
        ->assertStatus(401)
        ->assertJsonPath('errors.code', 'SESSION_REVOKED');
});

it('rejects a refresh attempt with no token at all', function (): void {
    $this->postJson('/api/v1/auth/refresh')
        ->assertStatus(401)
        ->assertJsonPath('errors.code', 'SESSION_REVOKED');
});

it('rejects an expired refresh token', function (): void {
    [$user, $refresh] = loginAndGetRefresh();

    RefreshToken::query()
        ->where('token_hash', hash('sha256', $refresh))
        ->update(['expires_at' => now()->subDay()]);

    $this->withHeader('X-Refresh-Token', $refresh)
        ->postJson('/api/v1/auth/refresh')
        ->assertStatus(401);
});

it('lets a newly registered, unverified student refresh their session', function (): void {
    /*
     * Regression: rotate() previously required status === active, which locked out
     * every new registration once their 15-minute access token expired. Caught by an
     * end-to-end smoke test, not by the unit tests, because the factory creates
     * already-active users.
     */
    $registration = $this->postJson('/api/v1/auth/register', [
        'name' => 'Brand New',
        'email' => 'brandnew@example.com',
        'password' => 'correct-horse-42',
        'password_confirmation' => 'correct-horse-42',
        'mobile' => true,
    ])->assertCreated()->json('data');

    expect(User::query()->where('email', 'brandnew@example.com')->firstOrFail()->status)
        ->toBe(UserStatus::PendingVerification);

    $this->withHeader('X-Refresh-Token', $registration['refresh_token'])
        ->postJson('/api/v1/auth/refresh')
        ->assertOk()
        ->assertJsonPath('success', true);
});

it('refuses to refresh a suspended account', function (): void {
    [$user, $refresh] = loginAndGetRefresh();

    $user->forceFill(['status' => UserStatus::Suspended])->save();

    $this->withHeader('X-Refresh-Token', $refresh)
        ->postJson('/api/v1/auth/refresh')
        ->assertStatus(401)
        ->assertJsonPath('errors.code', 'SESSION_REVOKED');
});

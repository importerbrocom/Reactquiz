<?php

declare(strict_types=1);

it('wraps successful responses in the standard envelope', function (): void {
    $response = $this->getJson('/api/v1/ping')->assertOk();

    expect($response->json())->toBeApiEnvelope(true);
    expect($response->json('errors'))->toBeNull();
});

it('wraps failures in the same envelope with a machine-readable code', function (): void {
    $response = $this->getJson('/api/v1/auth/me')->assertStatus(401);

    expect($response->json())->toBeApiEnvelope(false);
    expect($response->json('errors.code'))->toBe('UNAUTHENTICATED')
        ->and($response->json('data'))->toBeNull();
});

it('returns field-keyed errors for validation failures', function (): void {
    $response = $this->postJson('/api/v1/auth/login', [])->assertStatus(422);

    expect($response->json('errors'))->toHaveKeys(['email', 'password'])
        ->and($response->json('errors.email.0'))->toBeString();
});

it('returns 404 with a non-revealing code for unknown routes', function (): void {
    $this->getJson('/api/v1/does-not-exist')
        ->assertStatus(404)
        ->assertJsonPath('errors.code', 'NOT_FOUND');
});

it('returns 405 for the wrong method', function (): void {
    $this->getJson('/api/v1/auth/login')
        ->assertStatus(405)
        ->assertJsonPath('errors.code', 'METHOD_NOT_ALLOWED');
});

it('advertises the api contract version on every response', function (): void {
    $response = $this->getJson('/api/v1/ping');

    expect($response->headers->get('X-Api-Contract'))
        ->toBe((string) config('security.api_contract_version'));
});

it('sends hardening headers on every response', function (): void {
    $response = $this->getJson('/api/v1/ping');

    expect($response->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($response->headers->get('X-Frame-Options'))->toBe('DENY')
        ->and($response->headers->get('Referrer-Policy'))->toBe('strict-origin-when-cross-origin')
        ->and($response->headers->get('Content-Security-Policy'))->toContain("default-src 'none'");
});

it('rate limits repeated login attempts', function (): void {
    foreach (range(1, 6) as $ignored) {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'flood@example.com',
            'password' => 'whatever-99',
        ]);
    }

    expect($response->status())->toBe(429);
    expect($response->json('errors.code'))->toBe('RATE_LIMITED');
    expect($response->json('meta.retry_after'))->toBeInt();
});

it('exposes readiness and liveness endpoints', function (): void {
    $this->getJson('/api/v1/ping')->assertOk()->assertJsonPath('data.time', fn ($t) => is_string($t));

    $this->getJson('/api/v1/health/ready')
        ->assertOk()
        ->assertJsonPath('data.database', 'ok')
        ->assertJsonPath('data.cache', 'ok');
});

it('serves the bootstrap payload without authentication', function (): void {
    $this->getJson('/api/v1/bootstrap')
        ->assertOk()
        ->assertJsonPath('data.api_contract_version', (int) config('security.api_contract_version'))
        ->assertJsonStructure(['data' => ['app_name', 'quiz_defaults']]);
});

<?php

declare(strict_types=1);

use App\Support\ApiResponse;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Idempotency is what makes offline answer sync safe: the outbox may resend the
 * same submission after a crash, a reinstall or a flaky connection, and the server
 * must record it exactly once.
 *
 * A throwaway route is registered here so the middleware can be tested in
 * isolation, before the quiz endpoints exist in Phase 3.
 */
beforeEach(function (): void {
    Route::post('/api/v1/_test/idempotent', function () {
        // A side effect we can count.
        cache()->increment('idem-test-calls');

        return ApiResponse::success([
            'call' => cache()->get('idem-test-calls'),
            'nonce' => Str::random(8),
        ], 'Recorded.');
    })->middleware(['api', 'auth:sanctum', 'idempotent'])->name('test.idempotent');

    cache()->forever('idem-test-calls', 0);
    actingAsStudent();
});

it('rejects a write with no idempotency key', function (): void {
    $this->postJson('/api/v1/_test/idempotent', ['answer' => 'a'])
        ->assertStatus(400)
        ->assertJsonPath('errors.code', 'MALFORMED_REQUEST');

    expect(cache()->get('idem-test-calls'))->toBe(0);
});

it('rejects a malformed idempotency key', function (): void {
    $this->withHeader('Idempotency-Key', 'short')
        ->postJson('/api/v1/_test/idempotent', ['answer' => 'a'])
        ->assertStatus(400);
});

it('executes once and replays the stored response for a repeat', function (): void {
    $key = (string) Str::uuid7();
    $body = ['answer' => 'a', 'client_answer_uuid' => (string) Str::uuid7()];

    $first = $this->withHeader('Idempotency-Key', $key)
        ->postJson('/api/v1/_test/idempotent', $body)
        ->assertOk();

    $second = $this->withHeader('Idempotency-Key', $key)
        ->postJson('/api/v1/_test/idempotent', $body)
        ->assertOk();

    // The handler ran exactly once...
    expect(cache()->get('idem-test-calls'))->toBe(1)
        // ...and the client received a byte-identical response, not a new one.
        ->and($second->json('data.nonce'))->toBe($first->json('data.nonce'))
        ->and($second->headers->get('X-Idempotent-Replay'))->toBe('true');
});

it('replays consistently across many concurrent-style retries', function (): void {
    $key = (string) Str::uuid7();
    $body = ['answer' => 'b'];

    $responses = collect(range(1, 20))->map(
        fn (): string => $this->withHeader('Idempotency-Key', $key)
            ->postJson('/api/v1/_test/idempotent', $body)
            ->json('data.nonce'),
    );

    expect(cache()->get('idem-test-calls'))->toBe(1)
        ->and($responses->unique())->toHaveCount(1);
});

it('rejects the same key used with a different payload', function (): void {
    $key = (string) Str::uuid7();

    $this->withHeader('Idempotency-Key', $key)
        ->postJson('/api/v1/_test/idempotent', ['answer' => 'a'])
        ->assertOk();

    // A genuine client bug: same key, different body. Better to fail loudly than
    // to silently return the wrong answer's result.
    $this->withHeader('Idempotency-Key', $key)
        ->postJson('/api/v1/_test/idempotent', ['answer' => 'd'])
        ->assertStatus(409)
        ->assertJsonPath('errors.code', 'DUPLICATE_SUBMISSION');

    expect(cache()->get('idem-test-calls'))->toBe(1);
});

it('lets a different key through as a separate operation', function (): void {
    foreach (range(1, 3) as $ignored) {
        $this->withHeader('Idempotency-Key', (string) Str::uuid7())
            ->postJson('/api/v1/_test/idempotent', ['answer' => 'a'])
            ->assertOk();
    }

    expect(cache()->get('idem-test-calls'))->toBe(3);
});

it('releases the key when the request fails so a corrected retry can succeed', function (): void {
    Route::post('/api/v1/_test/failing', fn () => ApiResponse::error('Nope.', 422, 'VALIDATION_FAILED'))
        ->middleware(['api', 'auth:sanctum', 'idempotent'])->name('test.failing');

    $key = (string) Str::uuid7();

    $this->withHeader('Idempotency-Key', $key)
        ->postJson('/api/v1/_test/failing', ['x' => 1])
        ->assertStatus(422);

    // Not stored as a completed operation, so the same key is reusable.
    $this->withHeader('Idempotency-Key', $key)
        ->postJson('/api/v1/_test/failing', ['x' => 1])
        ->assertStatus(422);
});

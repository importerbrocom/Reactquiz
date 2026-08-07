<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use App\Support\CacheKeys;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Replay protection for writes that an offline outbox may resend.
 *
 * This is the fast path only. The durable guarantee is the unique constraint on
 * quiz_attempt_answers(quiz_attempt_id, client_answer_uuid), which still holds if
 * the cache is flushed — see docs/phase-1/04-api-contract.md section 6.
 */
final class IdempotencyKey
{
    private const HEADER = 'Idempotency-Key';

    public function handle(Request $request, Closure $next, string $required = 'required'): Response
    {
        $key = $request->header(self::HEADER);

        if (blank($key)) {
            if ($required !== 'required') {
                return $next($request);
            }

            return ApiResponse::error(
                'This request requires an Idempotency-Key header.',
                400,
                'MALFORMED_REQUEST',
            );
        }

        if (! preg_match('/^[A-Za-z0-9\-_]{8,64}$/', $key)) {
            return ApiResponse::error('Malformed Idempotency-Key header.', 400, 'MALFORMED_REQUEST');
        }

        $user = $request->user();
        $cacheKey = CacheKeys::idempotency(
            $user?->getKey() ?? 0,
            $request->route()?->getName() ?? $request->path(),
            $key,
        );
        $fingerprint = hash('sha256', $request->getContent() ?: '{}');
        $ttl = now()->addHours((int) config('security.idempotency_ttl_hours'));

        // Atomic claim. add() only succeeds if the key does not already exist.
        $claimed = Cache::add($cacheKey, ['state' => 'in_flight', 'fingerprint' => $fingerprint], $ttl);

        if (! $claimed) {
            /** @var array{state?: string, fingerprint?: string, status?: int, body?: string} $stored */
            $stored = Cache::get($cacheKey, []);

            if (($stored['fingerprint'] ?? null) !== $fingerprint) {
                return ApiResponse::error(
                    'This idempotency key was already used with a different request.',
                    409,
                    'DUPLICATE_SUBMISSION',
                );
            }

            if (($stored['state'] ?? null) === 'completed') {
                return response(
                    $stored['body'] ?? '',
                    $stored['status'] ?? 200,
                )->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Idempotent-Replay' => 'true',
                    'Cache-Control' => 'no-store',
                ]);
            }

            // Still processing: tell the client to wait and re-read state rather
            // than resubmitting, which would double-count.
            return ApiResponse::error(
                'A matching request is already being processed.',
                409,
                'DUPLICATE_SUBMISSION',
                meta: ['retry_after' => 1],
            )->header('Retry-After', '1');
        }

        $response = $next($request);

        if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            Cache::put($cacheKey, [
                'state' => 'completed',
                'fingerprint' => $fingerprint,
                'status' => $response->getStatusCode(),
                'body' => $response->getContent(),
            ], $ttl);
        } else {
            // Failures release the key so a corrected retry is possible.
            Cache::forget($cacheKey);
        }

        return $response;
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * CSRF-like protection for cookie-bearing auth endpoints.
 *
 * Per docs/phase-1/08-security-architecture.md §3.1:
 * "/auth/refresh and /auth/logout additionally require an X-Requested-With-style
 * custom header that a cross-site form cannot set."
 *
 * Simple tokens or fetch requests include this header; HTML forms cannot.
 * This prevents CSRF attacks on cookie-authenticated endpoints without needing
 * a full CSRF token flow (which doesn't work for API-only apps).
 */
class EnsureCustomHeader
{
    private const HEADER = 'X-Requested-With';

    public function handle(Request $request, Closure $next): Response
    {
        // Only enforce on state-changing methods that use cookies
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $next($request);
        }

        if (! $request->hasHeader(self::HEADER)) {
            return ApiResponse::error(
                'Missing required header.',
                Response::HTTP_FORBIDDEN,
                'CSRF_HEADER_MISSING',
            );
        }

        return $next($request);
    }
}

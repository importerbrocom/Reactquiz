<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforce admin IP allowlist when configured.
 *
 * When ADMIN_IP_ALLOWLIST is set (comma-separated), admin routes are restricted
 * to those IPs. If empty/unset, all IPs are allowed (development mode).
 *
 * Per docs/phase-1/08-security-architecture.md §3.3:
 * "admin routes additionally check an IP allow-list when ADMIN_IP_ALLOWLIST is set"
 */
class EnsureAdminIpAllowed
{
    public function handle(Request $request, Closure $next): Response
    {
        $allowlist = config('security.admin_ip_allowlist', []);

        // If no allowlist is configured, allow all (development/staging)
        if (empty($allowlist)) {
            return $next($request);
        }

        $clientIp = $request->ip();

        if (! in_array($clientIp, $allowlist, true)) {
            return ApiResponse::error(
                'Access denied from this IP address.',
                Response::HTTP_FORBIDDEN,
                'IP_NOT_ALLOWED',
            );
        }

        return $next($request);
    }
}

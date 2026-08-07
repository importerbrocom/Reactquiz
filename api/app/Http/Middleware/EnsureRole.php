<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Second authorisation gate, after Sanctum token abilities.
 *
 * Deliberately reads the DB-backed `users.role` column rather than the token's
 * abilities, so revoking a role takes effect on the very next request instead of
 * waiting for the access token to expire.
 */
final class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if ($user === null) {
            return ApiResponse::error('Unauthenticated.', 401, 'UNAUTHENTICATED');
        }

        if (! in_array($user->role->value, $roles, true)) {
            return ApiResponse::error(
                'You do not have permission to perform this action.',
                403,
                'FORBIDDEN_ROLE',
            );
        }

        return $next($request);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\UserStatus;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rejects tokens belonging to suspended accounts, unverified emails (when
 * required) and — importantly — tokens minted before a global session
 * invalidation such as "log out all devices" or a password reset.
 */
final class EnsureAccountIsUsable
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return ApiResponse::error('Unauthenticated.', 401, 'UNAUTHENTICATED');
        }

        if ($user->status === UserStatus::Suspended) {
            return ApiResponse::error('This account has been suspended.', 403, 'ACCOUNT_SUSPENDED');
        }

        $token = $user->currentAccessToken();

        if ($token instanceof PersonalAccessToken
            && $user->sessions_valid_after !== null
            && $token->created_at !== null
            && $token->created_at->lt($user->sessions_valid_after)) {
            return ApiResponse::error(
                'Your session has been revoked. Please sign in again.',
                401,
                'SESSION_REVOKED',
            );
        }

        if (config('security.require_verified_email')
            && $user->isStudent()
            && $user->email_verified_at === null) {
            return ApiResponse::error(
                'Please verify your email address to continue.',
                403,
                'EMAIL_NOT_VERIFIED',
            );
        }

        return $next($request);
    }
}

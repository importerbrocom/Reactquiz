<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Exceptions\SessionRevokedException;
use App\Http\Controllers\Concerns\HandlesRefreshTokens;
use App\Models\User;
use App\Services\Auth\TokenService;
use App\Services\Support\ActivityLogger;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

final class SessionController
{
    use HandlesRefreshTokens;

    public function __construct(
        private readonly TokenService $tokens,
        private readonly ActivityLogger $activity,
    ) {}

    /** POST /auth/refresh — rotates the refresh token and mints a new access token. */
    public function refresh(Request $request): JsonResponse
    {
        $plain = $this->readRefreshToken($request);

        if ($plain === null) {
            throw new SessionRevokedException('No refresh token was provided.');
        }

        $result = $this->tokens->rotate($plain, $request);

        return $this->respondWithTokens($request, $result, 'Session refreshed.');
    }

    /** POST /auth/logout — this device only. */
    public function logout(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->tokens->revokeCurrent($user, $this->readRefreshToken($request));
        $this->activity->log('auth.logout', $user);

        return ApiResponse::sensitive(null, 'Signed out.')
            ->withCookie($this->forgetRefreshCookie());
    }

    /** POST /auth/logout-all — every device. Requires the password as a re-auth step. */
    public function logoutAll(Request $request): JsonResponse
    {
        $request->validate(['password' => ['required', 'string']]);

        /** @var User $user */
        $user = $request->user();

        if (! Hash::check((string) $request->input('password'), $user->password)) {
            return ApiResponse::error('The provided password is incorrect.', 422, 'VALIDATION_FAILED', [
                'password' => ['The provided password is incorrect.'],
            ]);
        }

        $this->tokens->revokeAll($user);
        $this->activity->log('auth.logout_all', $user, severity: 'security');

        return ApiResponse::sensitive(null, 'Signed out of all devices.')
            ->withCookie($this->forgetRefreshCookie());
    }
}

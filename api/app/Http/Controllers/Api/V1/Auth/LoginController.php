<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Enums\UserStatus;
use App\Exceptions\AccountLockedException;
use App\Http\Controllers\Concerns\HandlesRefreshTokens;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Services\Auth\LoginThrottleService;
use App\Services\Auth\TokenService;
use App\Services\Support\ActivityLogger;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

final class LoginController
{
    use HandlesRefreshTokens;

    /**
     * A valid bcrypt hash of a random value. Verified when the account does not
     * exist so that response timing does not reveal whether an email is registered.
     */
    private const DUMMY_HASH = '$2y$12$C6UzMDM.H6dfI/f/IKcEe.rPtaSpAWLuLcSSTV3.dGkYyeAkxA0Ni';

    public function __construct(
        private readonly TokenService $tokens,
        private readonly LoginThrottleService $throttle,
        private readonly ActivityLogger $activity,
    ) {}

    public function __invoke(LoginRequest $request): JsonResponse
    {
        $email = (string) $request->validated('email');
        $user = User::query()->where('email', $email)->first();

        if ($this->throttle->isLocked($user)) {
            $this->throttle->recordFailure($email, $user, $request, 'locked');
            $this->activity->log('auth.login_locked', $user, severity: 'security');

            throw new AccountLockedException;
        }

        $passwordMatches = Hash::check(
            (string) $request->validated('password'),
            $user?->password ?? self::DUMMY_HASH,
        );

        if ($user === null || ! $passwordMatches) {
            $this->throttle->recordFailure($email, $user, $request);
            $this->activity->log('auth.login_failed', $user, severity: 'security');

            // Identical message and shape whether the account exists or not.
            return ApiResponse::error(
                'Invalid credentials, or the account is temporarily locked.',
                401,
                'INVALID_CREDENTIALS',
            );
        }

        if ($user->status === UserStatus::Suspended) {
            $this->throttle->recordFailure($email, $user, $request, 'suspended');

            return ApiResponse::error('This account has been suspended.', 403, 'ACCOUNT_SUSPENDED');
        }

        $this->throttle->recordSuccess($user, $request);
        $this->activity->log('auth.login', $user);

        return $this->respondWithTokens(
            $request,
            [...$this->tokens->issue($user, $request), 'user' => $user],
            'Signed in successfully.',
        );
    }
}

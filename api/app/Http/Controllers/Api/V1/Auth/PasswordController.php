<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use App\Services\Auth\TokenService;
use App\Services\Support\ActivityLogger;
use App\Support\ApiResponse;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

final class PasswordController
{
    public function __construct(
        private readonly TokenService $tokens,
        private readonly ActivityLogger $activity,
    ) {}

    /**
     * POST /auth/forgot-password
     *
     * Always returns 200 regardless of whether the address exists — anything else
     * turns this endpoint into an account-enumeration oracle.
     */
    public function forgot(ForgotPasswordRequest $request): JsonResponse
    {
        Password::sendResetLink($request->only('email'));

        return ApiResponse::success(
            null,
            'If that email address is registered, a reset link is on its way.',
        );
    }

    /** POST /auth/reset-password */
    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                // A password reset invalidates every existing session everywhere.
                $this->tokens->revokeAll($user, 'password_reset');

                event(new PasswordReset($user));
                $this->activity->log('auth.password_reset', $user, severity: 'security');
            },
        );

        if ($status !== Password::PasswordReset) {
            return ApiResponse::error(
                'This password reset link is invalid or has expired.',
                422,
                'VALIDATION_FAILED',
                ['email' => [__($status)]],
            );
        }

        return ApiResponse::success(null, 'Password reset. Please sign in again.');
    }

    /** POST /auth/change-password */
    public function change(ChangePasswordRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->forceFill(['password' => $request->validated('password')])->save();

        if ($request->boolean('logout_other_devices', true)) {
            $this->tokens->revokeAll($user, 'password_changed');
        }

        $this->activity->log('auth.password_changed', $user, severity: 'security');

        return ApiResponse::sensitive(
            null,
            'Password updated. Please sign in again on your other devices.',
        );
    }
}

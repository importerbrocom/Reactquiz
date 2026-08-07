<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Enums\UserStatus;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class EmailVerificationController
{
    /** GET /auth/email/verify/{id}/{hash} — signed URL from the emailed link. */
    public function verify(EmailVerificationRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return ApiResponse::success(null, 'Email address already verified.');
        }

        $user->markEmailAsVerified();
        $user->forceFill(['status' => UserStatus::Active])->save();

        event(new Verified($user));

        return ApiResponse::success(null, 'Email address verified.');
    }

    /** POST /auth/email/resend */
    public function resend(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return ApiResponse::success(null, 'Email address already verified.');
        }

        $user->sendEmailVerificationNotification();

        return ApiResponse::accepted(null, 'Verification email sent.');
    }
}

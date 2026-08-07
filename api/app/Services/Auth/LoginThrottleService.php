<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\LoginAttempt;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Progressive account lockout plus a hashed audit trail.
 *
 * Two deliberate choices:
 *   - Emails are stored hashed, so failed logins for accounts that do not exist
 *     never accumulate a plaintext list of addresses.
 *   - Responses and timing are identical whether or not the account exists, so the
 *     endpoint cannot be used to enumerate users.
 */
final class LoginThrottleService
{
    public function isLocked(?User $user): bool
    {
        return $user !== null && $user->isLocked();
    }

    public function recordFailure(string $email, ?User $user, Request $request, string $reason = 'invalid_credentials'): void
    {
        LoginAttempt::query()->create([
            'email_hash' => LoginAttempt::hashEmail($email),
            'user_id' => $user?->getKey(),
            'ip_address' => $request->ip(),
            'successful' => false,
            'failure_reason' => $reason,
            'user_agent' => str((string) $request->userAgent())->limit(250, '')->toString(),
        ]);

        if ($user === null) {
            return;
        }

        $attempts = $user->failed_login_attempts + 1;
        $lockMinutes = $this->lockoutMinutesFor($attempts);

        $user->forceFill([
            'failed_login_attempts' => $attempts,
            'locked_until' => $lockMinutes > 0 ? now()->addMinutes($lockMinutes) : $user->locked_until,
        ])->save();
    }

    public function recordSuccess(User $user, Request $request): void
    {
        LoginAttempt::query()->create([
            'email_hash' => LoginAttempt::hashEmail($user->email),
            'user_id' => $user->getKey(),
            'ip_address' => $request->ip(),
            'successful' => true,
            'user_agent' => str((string) $request->userAgent())->limit(250, '')->toString(),
        ]);

        $user->forceFill([
            'failed_login_attempts' => 0,
            'locked_until' => null,
            'last_login_at' => now(),
            'last_active_at' => now(),
        ])->save();
    }

    /** Highest matching tier wins: 12 failures gives 30 minutes, not 1. */
    private function lockoutMinutesFor(int $attempts): int
    {
        $tiers = (array) config('security.login.lockout_tiers');
        krsort($tiers);

        foreach ($tiers as $threshold => $minutes) {
            if ($attempts >= (int) $threshold) {
                return (int) $minutes;
            }
        }

        return 0;
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Enums\UserStatus;
use App\Exceptions\SessionRevokedException;
use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Access + refresh token issuance with rotation and reuse detection.
 *
 * Access token : Sanctum personal access token, 15 min, abilities = [role].
 * Refresh token: opaque 64-byte random string, stored only as a sha256 hash,
 *                grouped into a "family" so that replaying an already-rotated
 *                token can be recognised as theft and kill the whole family.
 *
 * See docs/phase-1/08-security-architecture.md section 3.
 */
final class TokenService
{
    /**
     * @return array{access_token: string, refresh_token: string, expires_in: int, family_id: string}
     */
    public function issue(User $user, ?Request $request = null, ?string $familyId = null): array
    {
        $accessTtl = (int) config('security.access_token_ttl_minutes');
        $refreshTtl = (int) config('security.refresh_token_ttl_days');

        $accessToken = $user->createToken(
            name: $this->deviceName($request),
            abilities: $user->tokenAbilities(),
            expiresAt: now()->addMinutes($accessTtl),
        );

        $plainRefresh = Str::random(64);

        RefreshToken::query()->create([
            'user_id' => $user->getKey(),
            'family_id' => $familyId ?? (string) Str::uuid7(),
            'token_hash' => $this->hash($plainRefresh),
            'access_token_id' => $accessToken->accessToken->getKey(),
            'device_name' => $this->deviceName($request),
            'device_hash' => $this->deviceHash($request),
            'ip_address' => $request?->ip(),
            'user_agent' => Str::limit((string) $request?->userAgent(), 250, ''),
            'expires_at' => now()->addDays($refreshTtl),
        ]);

        return [
            'access_token' => $accessToken->plainTextToken,
            'refresh_token' => $plainRefresh,
            'expires_in' => $accessTtl * 60,
            'family_id' => $familyId ?? '',
        ];
    }

    /**
     * Exchange a refresh token for a new pair.
     *
     * @return array{user: User, access_token: string, refresh_token: string, expires_in: int}
     *
     * @throws SessionRevokedException
     */
    public function rotate(string $plainRefresh, ?Request $request = null): array
    {
        $record = RefreshToken::query()
            ->with('user')
            ->where('token_hash', $this->hash($plainRefresh))
            ->first();

        if ($record === null) {
            throw new SessionRevokedException;
        }

        // Reuse of a token that has already been rotated means it leaked.
        if ($record->wasAlreadyRotated()) {
            $this->revokeFamily($record->family_id, 'reuse_detected');

            throw new SessionRevokedException(
                'Your session was ended for security reasons. Please sign in again.',
            );
        }

        if (! $record->isUsable()) {
            throw new SessionRevokedException;
        }

        /** @var User|null $user */
        $user = $record->user;

        /*
         * Only SUSPENDED accounts are refused here. Checking for "is active" instead
         * would lock out every newly registered student, because they sit at
         * pending_verification until they click the emailed link — their access
         * token would expire after 15 minutes with no way to refresh, and if email
         * verification is required they could not recover at all.
         * Verification is enforced separately by EnsureAccountIsUsable on the routes
         * that need it, which is the right place for it.
         */
        if ($user === null
            || $user->status === UserStatus::Suspended
            || $this->issuedBeforeGlobalInvalidation($record, $user)) {
            throw new SessionRevokedException;
        }

        return DB::transaction(function () use ($record, $user, $request): array {
            $record->forceFill([
                'rotated_at' => now(),
                'last_used_at' => now(),
            ])->save();

            // The paired access token dies with the refresh token it was issued beside.
            if ($record->access_token_id !== null) {
                PersonalAccessToken::query()->whereKey($record->access_token_id)->delete();
            }

            $fresh = $this->issue($user, $request, $record->family_id);

            return [
                'user' => $user,
                'access_token' => $fresh['access_token'],
                'refresh_token' => $fresh['refresh_token'],
                'expires_in' => $fresh['expires_in'],
            ];
        });
    }

    /** Revoke the current access token and its paired refresh token only. */
    public function revokeCurrent(User $user, ?string $plainRefresh = null): void
    {
        $token = $user->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            RefreshToken::query()
                ->where('access_token_id', $token->getKey())
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now(), 'revoked_reason' => 'logout']);

            $token->delete();
        }

        if ($plainRefresh !== null) {
            RefreshToken::query()
                ->where('token_hash', $this->hash($plainRefresh))
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now(), 'revoked_reason' => 'logout']);
        }
    }

    /**
     * Log out everywhere. `sessions_valid_after` is the cheap global kill switch:
     * any token issued before this instant is rejected without enumerating tokens.
     */
    public function revokeAll(User $user, string $reason = 'logout_all'): void
    {
        DB::transaction(function () use ($user, $reason): void {
            $user->tokens()->delete();

            RefreshToken::query()
                ->where('user_id', $user->getKey())
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now(), 'revoked_reason' => $reason]);

            $user->forceFill(['sessions_valid_after' => now()])->save();
        });
    }

    public function revokeFamily(string $familyId, string $reason): void
    {
        $tokens = RefreshToken::query()->where('family_id', $familyId)->get();

        $accessTokenIds = $tokens->pluck('access_token_id')->filter()->all();

        if ($accessTokenIds !== []) {
            PersonalAccessToken::query()->whereKey($accessTokenIds)->delete();
        }

        RefreshToken::query()
            ->where('family_id', $familyId)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now(), 'revoked_reason' => $reason]);
    }

    public function revokeDevice(User $user, int $refreshTokenId): bool
    {
        $token = RefreshToken::query()
            ->where('user_id', $user->getKey())
            ->whereKey($refreshTokenId)
            ->first();

        if ($token === null) {
            return false;
        }

        $this->revokeFamily($token->family_id, 'device_revoked');

        return true;
    }

    public function hash(string $plain): string
    {
        return hash('sha256', $plain);
    }

    private function issuedBeforeGlobalInvalidation(RefreshToken $token, User $user): bool
    {
        return $user->sessions_valid_after !== null
            && $token->created_at !== null
            && $token->created_at->lt($user->sessions_valid_after);
    }

    private function deviceName(?Request $request): string
    {
        $supplied = $request?->input('device_name');

        if (is_string($supplied) && trim($supplied) !== '') {
            return Str::limit(trim($supplied), 100, '');
        }

        return Str::limit((string) ($request?->userAgent() ?? 'unknown device'), 100, '');
    }

    private function deviceHash(?Request $request): ?string
    {
        if ($request === null) {
            return null;
        }

        return hash('sha256', implode('|', [
            $request->userAgent() ?? '',
            $request->header('sec-ch-ua-platform', ''),
        ]));
    }
}

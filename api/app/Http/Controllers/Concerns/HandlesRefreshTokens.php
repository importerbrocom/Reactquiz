<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Http\Resources\AuthSessionResource;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * One auth endpoint set, two clients.
 *
 * Web: refresh token in an HttpOnly, Secure, SameSite=Strict cookie scoped to
 *      /api/v1/auth, so XSS cannot read it and it is not attached to normal calls.
 * Mobile (React Native): no dependable cookie jar, so the token travels in the
 *      X-Refresh-Token header and comes back in the response body for storage in
 *      the iOS Keychain via expo-secure-store.
 *
 * The channel is chosen by how the client sent it, never by a query parameter.
 */
trait HandlesRefreshTokens
{
    protected function clientPrefersHeaderToken(Request $request): bool
    {
        return $request->hasHeader(config('security.refresh_header'))
            || $request->boolean('mobile');
    }

    protected function readRefreshToken(Request $request): ?string
    {
        $header = $request->header(config('security.refresh_header'));

        if (is_string($header) && $header !== '') {
            return $header;
        }

        $cookie = $request->cookie(config('security.refresh_cookie.name'));

        return is_string($cookie) && $cookie !== '' ? $cookie : null;
    }

    /**
     * @param  array{user: User, access_token: string, refresh_token: string, expires_in: int}  $tokens
     */
    protected function respondWithTokens(Request $request, array $tokens, string $message, int $status = 200): JsonResponse
    {
        $useHeader = $this->clientPrefersHeaderToken($request);

        $payload = [
            'user' => $tokens['user'],
            'access_token' => $tokens['access_token'],
            'expires_in' => $tokens['expires_in'],
        ];

        if ($useHeader) {
            $payload['refresh_token'] = $tokens['refresh_token'];
        }

        $response = ApiResponse::sensitive(
            new AuthSessionResource($payload),
            $message,
            $status,
        );

        return $useHeader
            ? $response
            : $response->withCookie($this->refreshCookie($tokens['refresh_token']));
    }

    protected function refreshCookie(string $value): Cookie
    {
        $config = config('security.refresh_cookie');

        return new Cookie(
            name: $config['name'],
            value: $value,
            expire: now()->addDays((int) config('security.refresh_token_ttl_days'))->getTimestamp(),
            path: $config['path'],
            domain: $config['domain'],
            secure: (bool) $config['secure'],
            httpOnly: true,
            raw: false,
            sameSite: $config['same_site'],
        );
    }

    protected function forgetRefreshCookie(): Cookie
    {
        $config = config('security.refresh_cookie');

        return new Cookie(
            name: $config['name'],
            value: '',
            expire: 1,
            path: $config['path'],
            domain: $config['domain'],
            secure: (bool) $config['secure'],
            httpOnly: true,
            raw: false,
            sameSite: $config['same_site'],
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Login / refresh payload.
 *
 * The refresh token is intentionally NOT part of this resource for web clients —
 * it travels in an HttpOnly cookie. It is only echoed in the body when the client
 * identifies as mobile, which has no cookie jar we want to rely on.
 *
 * @property-read array{user: User, access_token: string, expires_in: int, refresh_token?: string} $resource
 */
final class AuthSessionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return array_filter([
            'user' => (new UserResource($this->resource['user']))->toArray($request),
            'access_token' => $this->resource['access_token'],
            'token_type' => 'Bearer',
            'expires_in' => $this->resource['expires_in'],
            'refresh_token' => $this->resource['refresh_token'] ?? null,
        ], static fn (mixed $v): bool => $v !== null);
    }
}

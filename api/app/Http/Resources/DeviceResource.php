<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\RefreshToken;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin RefreshToken */
final class DeviceResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'device_name' => $this->device_name,
            'ip_address' => $this->ip_address,
            'last_used_at' => $this->last_used_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'expires_at' => $this->expires_at->toIso8601String(),
            'is_current' => $this->access_token_id !== null
                && $request->user()?->currentAccessToken()?->getKey() === $this->access_token_id,
        ];
    }
}

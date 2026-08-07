<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
final class UserResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,           // uuid, never the sequential key
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'avatar_url' => $this->avatar_path !== null
                ? asset('storage/'.$this->avatar_path)
                : null,
            'role' => $this->role->value,
            'status' => $this->status->value,
            'timezone' => $this->timezone,
            'locale' => $this->locale,
            'email_verified' => $this->email_verified_at !== null,
            'onboarding_completed' => $this->onboarding_completed_at !== null,
        ];
    }
}

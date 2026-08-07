<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Resources\DeviceResource;
use App\Models\User;
use App\Services\Auth\TokenService;
use App\Services\Support\ActivityLogger;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DeviceController
{
    public function __construct(
        private readonly TokenService $tokens,
        private readonly ActivityLogger $activity,
    ) {}

    /** GET /auth/devices */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $devices = $user->refreshTokens()
            ->usable()
            ->latest('last_used_at')
            ->limit(50)
            ->get();

        return ApiResponse::success(
            DeviceResource::collection($devices)->toArray($request),
            'Active devices retrieved.',
        );
    }

    /** DELETE /auth/devices/{device} */
    public function destroy(Request $request, int $device): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $this->tokens->revokeDevice($user, $device)) {
            return ApiResponse::error('Device not found.', 404, 'NOT_FOUND');
        }

        $this->activity->log('auth.device_revoked', $user, severity: 'security', properties: [
            'refresh_token_id' => $device,
        ]);

        return ApiResponse::success(null, 'Device signed out.');
    }
}

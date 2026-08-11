<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Student;

use App\Enums\PushStatus;
use App\Enums\PushTransport;
use App\Http\Controllers\Controller;
use App\Models\PushSubscription;
use App\Services\Push\WebPushService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PushSubscriptionController extends Controller
{
    /**
     * POST /student/push-subscriptions — Upsert by endpoint hash.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'endpoint' => 'required|url|max:2048',
            'keys.p256dh' => 'required|string',
            'keys.auth' => 'required|string',
            'device_label' => 'nullable|string|max:120',
            'timezone' => 'nullable|string|max:64',
        ]);

        $user = $request->user();
        $endpoint = $request->input('endpoint');
        $hash = PushSubscription::hashEndpoint($endpoint);

        $subscription = PushSubscription::updateOrCreate(
            ['endpoint_hash' => $hash],
            [
                'user_id' => $user->id,
                'transport' => PushTransport::WebPush,
                'endpoint' => $endpoint,
                'public_key' => $request->input('keys.p256dh'),
                'auth_token' => $request->input('keys.auth'),
                'content_encoding' => 'aes128gcm',
                'device_label' => $request->input('device_label'),
                'user_agent' => $request->userAgent(),
                'timezone' => $request->input('timezone', $user->timezone),
                'status' => PushStatus::Active,
                'failure_count' => 0,
                'last_seen_at' => now(),
            ],
        );

        return ApiResponse::success(
            $subscription->only(['id', 'device_label', 'status', 'created_at']),
            'Push subscription registered.',
        );
    }

    /**
     * DELETE /student/push-subscriptions — Unsubscribe by endpoint.
     */
    public function destroy(Request $request): JsonResponse
    {
        $request->validate(['endpoint' => 'required|string']);

        $hash = PushSubscription::hashEndpoint($request->input('endpoint'));

        PushSubscription::where('endpoint_hash', $hash)
            ->where('user_id', $request->user()->id)
            ->update(['status' => PushStatus::Revoked]);

        return ApiResponse::success(null, 'Push subscription revoked.');
    }

    /**
     * POST /student/push-subscriptions/test — Send a test push (throttled 3/hour).
     */
    public function test(Request $request, WebPushService $pushService): JsonResponse
    {
        $request->validate(['endpoint' => 'required|string']);

        $hash = PushSubscription::hashEndpoint($request->input('endpoint'));
        $sub = PushSubscription::where('endpoint_hash', $hash)
            ->where('user_id', $request->user()->id)
            ->active()
            ->first();

        if (! $sub) {
            return ApiResponse::error('No active subscription found.', Response::HTTP_NOT_FOUND);
        }

        $success = $pushService->sendToSubscription($sub, [
            'title' => 'QuizPath Test',
            'body' => 'Push notifications are working!',
            'icon' => '/icons/icon-192.png',
            'tag' => 'test-push',
        ]);

        return ApiResponse::success(
            ['delivered' => $success],
            $success ? 'Test push sent successfully.' : 'Push delivery failed.',
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Shared;

use App\Models\Setting;
use App\Support\ApiResponse;
use App\Support\CacheKeys;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * One request instead of four on a cold start: public settings, feature flags and
 * the VAPID public key. Cached and ETag-friendly.
 */
final class BootstrapController
{
    public function __invoke(): JsonResponse
    {
        $payload = Cache::remember(
            CacheKeys::publicSettings(),
            CacheKeys::jitter(300),
            fn (): array => [
                'app_name' => config('app.name'),
                'api_contract_version' => (int) config('security.api_contract_version'),
                'vapid_public_key' => config('services.webpush.public_key'),
                'settings' => Setting::query()
                    ->public()
                    ->get(['group', 'key', 'value'])
                    ->groupBy('group')
                    ->map(fn ($rows) => $rows->pluck('value', 'key'))
                    ->toArray(),
                'quiz_defaults' => [
                    'total_quiz_days' => (int) config('quiz.defaults.total_quiz_days'),
                    'daily_question_count' => (int) config('quiz.defaults.daily_question_count'),
                    'total_levels' => (int) config('quiz.defaults.total_levels'),
                ],
            ],
        );

        return ApiResponse::success($payload, 'Bootstrap data retrieved.');
    }
}

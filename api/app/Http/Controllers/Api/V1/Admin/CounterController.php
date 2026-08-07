<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\ExamCategory;
use App\Models\Level;
use App\Models\Programme;
use App\Models\Question;
use App\Models\User;
use App\Support\ApiResponse;
use App\Support\CacheKeys;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * Headline counters for the admin dashboard.
 *
 * Cached deliberately: these are the only counts allowed to be computed with
 * COUNT(*). Everything time-series or per-student comes from report_daily_metrics,
 * never from a live scan of the answer log.
 */
final class CounterController
{
    public function __invoke(): JsonResponse
    {
        $counters = Cache::remember(
            CacheKeys::adminCounters(),
            CacheKeys::jitter(600),
            fn (): array => [
                'students' => User::query()->students()->count(),
                'active_students' => User::query()->students()->active()->count(),
                'exam_categories' => ExamCategory::query()->active()->count(),
                'programmes' => Programme::query()->active()->count(),
                'levels' => Level::query()->active()->count(),
                'questions' => Question::query()->active()->count(),
            ],
        );

        return ApiResponse::success($counters, 'Counters retrieved.');
    }
}

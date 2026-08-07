<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Shared\BootstrapController;
use App\Support\ApiResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('bootstrap', BootstrapController::class)
    ->middleware('throttle:public')
    ->name('bootstrap');

/** Liveness: deliberately touches nothing, so it stays up when the DB does not. */
Route::get('ping', fn () => ApiResponse::success(['time' => now()->toIso8601String()], 'pong'))
    ->name('ping');

/** Readiness: the load balancer drains a node that cannot reach its dependencies. */
Route::get('health/ready', function () {
    $checks = [];

    try {
        DB::connection()->getPdo();
        $checks['database'] = 'ok';
    } catch (Throwable) {
        $checks['database'] = 'fail';
    }

    try {
        Cache::store()->put('health', 1, 5);
        $checks['cache'] = Cache::store()->get('health') === 1 ? 'ok' : 'fail';
    } catch (Throwable) {
        $checks['cache'] = 'fail';
    }

    $healthy = ! in_array('fail', $checks, true);

    return ApiResponse::success($checks, $healthy ? 'Ready.' : 'Degraded.', $healthy ? 200 : 503);
})->name('health.ready');

<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Admin\CounterController;
use App\Http\Controllers\Api\V1\Admin\NotificationCampaignController;
use App\Http\Controllers\Api\V1\Admin\QuestionImportController;
use App\Jobs\PruneExpiredSubscriptionsJob;
use App\Models\PushSubscription;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
 * Admin surface. Four gates apply to every route here:
 *   auth:sanctum      — a valid access token
 *   abilities:admin   — the token itself was minted for an admin
 *   role:admin        — the DB-backed role, re-checked per request
 *   + a Policy call inside each controller action
 */
Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth:sanctum', 'abilities:admin', 'role:admin', 'account', 'throttle:api'])
    ->group(function (): void {
        Route::get('counters', CounterController::class)->name('counters');

        // Question imports (Phase 6)
        Route::prefix('question-imports')->name('question-imports.')->group(function (): void {
            Route::get('template', [QuestionImportController::class, 'template'])->name('template');
            Route::post('/', [QuestionImportController::class, 'store'])->name('store');
            Route::get('/', [QuestionImportController::class, 'index'])->name('index');
            Route::get('{uuid}', [QuestionImportController::class, 'show'])->name('show');
            Route::get('{uuid}/progress', [QuestionImportController::class, 'progress'])->name('progress');
            Route::get('{uuid}/items', [QuestionImportController::class, 'items'])->name('items');
            Route::post('{uuid}/approve', [QuestionImportController::class, 'approve'])->name('approve');
            Route::post('{uuid}/retry', [QuestionImportController::class, 'retry'])->name('retry');
            Route::delete('{uuid}', [QuestionImportController::class, 'destroy'])->name('destroy');
        });

        // Notification campaigns (Phase 7)
        Route::prefix('notification-campaigns')->name('campaigns.')->group(function (): void {
            Route::get('/', [NotificationCampaignController::class, 'index'])->name('index');
            Route::post('/', [NotificationCampaignController::class, 'store'])->name('store');
            Route::post('{id}/send', [NotificationCampaignController::class, 'send'])->name('send');
            Route::post('{id}/cancel', [NotificationCampaignController::class, 'cancel'])->name('cancel');
            Route::get('{id}/stats', [NotificationCampaignController::class, 'stats'])->name('stats');
        });

        // Push subscription health (Phase 7)
        Route::get('push-subscriptions/health', function () {
            $stats = PushSubscription::selectRaw("status, browser, count(*) as total")
                ->groupBy('status', 'browser')
                ->get();

            return ApiResponse::success($stats);
        })->name('push.health');

        Route::post('push-subscriptions/prune', function () {
            PruneExpiredSubscriptionsJob::dispatch();

            return ApiResponse::success(null, 'Pruning queued.', 202);
        })->name('push.prune');
    });

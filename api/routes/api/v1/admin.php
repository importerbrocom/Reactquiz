<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Admin\CounterController;
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
    });

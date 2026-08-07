<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Auth\CurrentUserController;
use App\Http\Controllers\Api\V1\Auth\DeviceController;
use App\Http\Controllers\Api\V1\Auth\EmailVerificationController;
use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\PasswordController;
use App\Http\Controllers\Api\V1\Auth\RegisterController;
use App\Http\Controllers\Api\V1\Auth\SessionController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->name('auth.')->group(function (): void {

    // ------------------------------------------------------------- public ----
    Route::post('register', RegisterController::class)
        ->middleware('throttle:auth-register')
        ->name('register');

    Route::post('login', LoginController::class)
        ->middleware('throttle:auth-login')
        ->name('login');

    // Reads the refresh token from an HttpOnly cookie (web) or the
    // X-Refresh-Token header (mobile). No access token required — by design.
    Route::post('refresh', [SessionController::class, 'refresh'])
        ->middleware('throttle:auth-refresh')
        ->name('refresh');

    Route::post('forgot-password', [PasswordController::class, 'forgot'])
        ->middleware('throttle:auth-login')
        ->name('password.forgot');

    Route::post('reset-password', [PasswordController::class, 'reset'])
        ->middleware('throttle:auth-login')
        ->name('password.reset');

    Route::get('email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    // ---------------------------------------------------- authenticated ----
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('logout', [SessionController::class, 'logout'])->name('logout');
        Route::post('logout-all', [SessionController::class, 'logoutAll'])->name('logout-all');

        Route::get('me', [CurrentUserController::class, 'show'])->name('me');
        Route::patch('me', [CurrentUserController::class, 'update'])->name('me.update');

        Route::post('change-password', [PasswordController::class, 'change'])->name('password.change');

        Route::post('email/resend', [EmailVerificationController::class, 'resend'])
            ->middleware('throttle:6,1')
            ->name('verification.resend');

        Route::get('devices', [DeviceController::class, 'index'])->name('devices.index');
        Route::delete('devices/{device}', [DeviceController::class, 'destroy'])
            ->whereNumber('device')
            ->name('devices.destroy');
    });
});

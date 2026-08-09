<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
 * Versioned API. A major version bump means a breaking change; additive but
 * client-relevant changes increment security.api_contract_version instead, which
 * is advertised on every response via the X-Api-Contract header.
 */
Route::prefix('v1')->name('api.v1.')->group(function (): void {
    require __DIR__.'/api/v1/auth.php';
    require __DIR__.'/api/v1/student.php';
    require __DIR__.'/api/v1/admin.php';
    require __DIR__.'/api/v1/shared.php';
});

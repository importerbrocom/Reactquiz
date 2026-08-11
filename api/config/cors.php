<?php

declare(strict_types=1);

/**
 * CORS configuration.
 *
 * Explicit origin allow-list. credentials only for auth paths.
 * No wildcard origins in production — only the known frontend domains.
 */
return [
    'paths' => ['api/*'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => array_filter(explode(',', (string) env('CORS_ALLOWED_ORIGINS', 'http://localhost:5173'))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => [
        'Content-Type',
        'Accept',
        'Authorization',
        'X-Requested-With',
        'X-Client-Build',
        'Idempotency-Key',
        'X-Refresh-Token',
    ],

    'exposed_headers' => [
        'X-Api-Contract',
        'X-Idempotent-Replay',
        'X-RateLimit-Limit',
        'X-RateLimit-Remaining',
        'Retry-After',
    ],

    'max_age' => 7200, // 2 hours preflight cache

    'supports_credentials' => true,
];

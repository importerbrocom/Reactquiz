<?php

declare(strict_types=1);

return [
    /*
     * Access tokens are short-lived and held in memory by the web client / Keychain
     * by the mobile client. Refresh tokens rotate on every use.
     */
    'access_token_ttl_minutes' => env('ACCESS_TOKEN_TTL_MINUTES', 15),
    'refresh_token_ttl_days' => env('REFRESH_TOKEN_TTL_DAYS', 30),

    'refresh_cookie' => [
        'name' => env('REFRESH_COOKIE_NAME', 'qp_refresh'),
        // Scoped so the cookie is not attached to ordinary API calls at all.
        'path' => env('REFRESH_COOKIE_PATH', '/api/v1/auth'),
        'domain' => env('REFRESH_COOKIE_DOMAIN'),
        'secure' => env('REFRESH_COOKIE_SECURE', true),
        'same_site' => 'strict',
    ],
    // React Native has no cookie jar we want to depend on, so mobile clients send
    // the refresh token in this header instead and receive it in the body.
    'refresh_header' => 'X-Refresh-Token',

    'login' => [
        'max_attempts' => env('AUTH_MAX_LOGIN_ATTEMPTS', 5),
        // Progressive: 5 failures => 1 min, 8 => 5 min, 12 => 30 min.
        'lockout_tiers' => [
            5 => 1,
            8 => 5,
            12 => 30,
        ],
        'attempt_window_minutes' => 15,
    ],

    'require_verified_email' => env('AUTH_REQUIRE_EMAIL_VERIFICATION', true),
    'admin_2fa_required' => env('AUTH_ADMIN_2FA_REQUIRED', false),
    'admin_ip_allowlist' => array_filter(explode(',', (string) env('ADMIN_IP_ALLOWLIST', ''))),

    'idempotency_ttl_hours' => env('QUIZ_IDEMPOTENCY_TTL_HOURS', 24),

    'api_contract_version' => (int) env('API_CONTRACT_VERSION', 1),
];

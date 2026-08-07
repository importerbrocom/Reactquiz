<?php

declare(strict_types=1);

return [
    // Seeder inputs read via config() so they still work with a cached config.
    'seed' => [
        'admin_email' => env('SEED_ADMIN_EMAIL', 'admin@quizpath.test'),
        'admin_name' => env('SEED_ADMIN_NAME', 'Platform Admin'),
        'admin_password' => env('SEED_ADMIN_PASSWORD', 'ChangeMe!2026'),
    ],

    'default_timezone' => env('QUIZ_DEFAULT_TIMEZONE', 'Asia/Kolkata'),

    'defaults' => [
        'total_levels' => env('QUIZ_DEFAULT_TOTAL_LEVELS', 6),
        'total_cycles' => env('QUIZ_DEFAULT_TOTAL_CYCLES', 2),
        'total_quiz_days' => env('QUIZ_DEFAULT_TOTAL_DAYS', 30),
        'daily_question_count' => env('QUIZ_DEFAULT_DAILY_QUESTIONS', 10),
        'test_day' => env('QUIZ_DEFAULT_TEST_DAY', 31),
        'pass_percentage' => env('QUIZ_DEFAULT_PASS_PERCENTAGE', 50),
        'test_attempt_limit' => env('QUIZ_DEFAULT_TEST_ATTEMPT_LIMIT', 0), // 0 = unlimited
    ],

    'attempt_abandon_hours' => env('QUIZ_ATTEMPT_ABANDON_HOURS', 12),

    // Answers arriving with a client timestamp outside this window are clamped.
    'answered_at_tolerance' => [
        'past_minutes' => 5,
        'future_minutes' => 2,
    ],

    'test' => [
        'window_size' => env('TEST_WINDOW_SIZE', 20),   // questions per windowed fetch
        'batch_max' => env('TEST_BATCH_MAX', 20),       // answers per batch sync
        'grace_seconds' => env('TEST_GRACE_SECONDS', 60),
    ],

    'answer_batch_max' => 20,

    // Flags an attempt for review rather than blocking it; false positives would
    // punish genuine learners.
    'suspicious_submissions_per_day' => env('QUIZ_SUSPICIOUS_SUBMISSIONS_PER_DAY', 25),

    'import' => [
        'max_rows' => env('IMPORT_MAX_ROWS', 5000),
        'max_upload_mb' => env('IMPORT_MAX_UPLOAD_MB', 10),
        'chunk_size' => env('IMPORT_CHUNK_SIZE', 500),
        'max_blank_rows' => 20,
    ],
];

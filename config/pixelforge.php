<?php

return [
    'base_url' => env('PIXELFORGE_BASE_URL', 'https://pixelforge.promptedit.dev/v1'),

    // Which api_keys.environment row this app uses (one key per environment).
    'environment' => env('PIXELFORGE_ENVIRONMENT', env('APP_ENV', 'local')),

    // Bootstrap values: `php artisan pixelforge:key` stores them encrypted in api_keys.
    'api_key' => env('PIXELFORGE_API_KEY'),
    'webhook_secret' => env('PIXELFORGE_WEBHOOK_SECRET'),

    // Users pay provider cost + markup. Basis points: 2500 = 25%.
    'markup_bps' => (int) env('PIXELFORGE_MARKUP_BPS', 2500),

    // Fake credits every new account starts with (USD, decimal string).
    'welcome_credit_usd' => env('PIXELFORGE_WELCOME_CREDIT_USD', '2.00'),

    // Provider limits (per account, across keys).
    'max_concurrent' => (int) env('PIXELFORGE_MAX_CONCURRENT', 3),
    'max_per_minute' => (int) env('PIXELFORGE_MAX_PER_MINUTE', 55), // a little under 60 for polling headroom

    'webhook_tolerance_seconds' => 300,
    'http_timeout_seconds' => 15,
    'log_retention_days' => (int) env('PIXELFORGE_LOG_RETENTION_DAYS', 14),
];

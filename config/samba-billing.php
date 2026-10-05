<?php

return [
    // Confirm the production hostname supplied by SambaSafety before use.
    'url' => env('SAMBA_BILLING_URL', 'https://billling.sambasafety.com'),

    'api_key' => env('SAMBA_BILLING_API_KEY'),

    // Samba notes some requests can take 10-20+ seconds.
    'timeout' => (int) env('SAMBA_BILLING_TIMEOUT', 60),
    'connect_timeout' => (int) env('SAMBA_BILLING_CONNECT_TIMEOUT', 15),

    'retry' => [
        'times' => (int) env('SAMBA_BILLING_RETRY_TIMES', 3),
        'sleep_ms' => (int) env('SAMBA_BILLING_RETRY_SLEEP_MS', 2000),
    ],
];

<?php

declare(strict_types=1);

return [
    'api_key' => env('POLI_PAGE_API_KEY'),
    'base_url' => env('POLI_PAGE_BASE_URL', 'https://api-develop.poli.page'),
    'timeout' => env('POLI_PAGE_TIMEOUT'),
    'user_agent' => env('POLI_PAGE_USER_AGENT'),
    'retries' => [
        'max_attempts' => env('POLI_PAGE_RETRY_MAX_ATTEMPTS'),
        'delay_seconds' => env('POLI_PAGE_RETRY_DELAY_SECONDS'),
    ],
    'http_client' => null,
    'request_factory' => null,
    'stream_factory' => null,
    'logger' => null,
    'on_retry' => null,
    'on_error' => null,
];

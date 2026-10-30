<?php

declare(strict_types=1);

return [
    'api_key' => env('POLI_PAGE_API_KEY'),
    'base_url' => env('POLI_PAGE_BASE_URL'),
    'timeout' => env('POLI_PAGE_TIMEOUT'),
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
    // Answers an uncaught PoliPageException with its toPayload() body and status.
    'error_handler' => env('POLI_PAGE_ERROR_HANDLER', true),
];

<?php

declare(strict_types=1);

return [
    'name' => 'Poli Page · Laravel demo',
    'env' => env('APP_ENV', 'local'),
    'debug' => (bool) env('APP_DEBUG', true),
    'url' => env('APP_URL', 'http://localhost:8000'),
    'timezone' => 'UTC',
    'locale' => 'en',
    'key' => env('APP_KEY', 'base64:0000000000000000000000000000000000000000000='),
    'cipher' => 'AES-256-CBC',
];

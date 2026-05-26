<?php

declare(strict_types=1);

// Why: Laravel 11 defaults to the 'database' session driver, which would
// require migrations + an SQLite/MySQL setup just to render a PDF. Use the
// 'array' driver since the demo doesn't actually need session persistence.
return [
    'driver' => 'array',
    'lifetime' => 120,
    'expire_on_close' => false,
    'encrypt' => false,
    'files' => storage_path('framework/sessions'),
    'connection' => null,
    'table' => 'sessions',
    'store' => null,
    'lottery' => [2, 100],
    'cookie' => 'poli_page_demo_session',
    'path' => '/',
    'domain' => null,
    'secure' => null,
    'http_only' => true,
    'same_site' => 'lax',
    'partitioned' => false,
];

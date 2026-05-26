<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Why: demo POSTs/DELETEs to /api/* are made from the same page via fetch()
        // without a CSRF token. Skipping CSRF on /api/* keeps the inline UI honest
        // (real apps would mount an api.php route file and not hit the web group).
        $middleware->validateCsrfTokens(except: ['api/*']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {})
    ->create();

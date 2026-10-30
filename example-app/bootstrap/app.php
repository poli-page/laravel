<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Why: the demo page POSTs and DELETEs these routes via fetch() without a
        // CSRF token. Real apps would mount an api.php route file instead.
        $middleware->validateCsrfTokens(except: ['render/*', 'documents', 'documents/*']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // poli-page/laravel registers its own handler: an uncaught PoliPageException
        // answers its toPayload() body with its status (404, 503, ...).
    })
    ->create();

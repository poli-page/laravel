<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use PoliPage\PoliPageException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Why: demo POSTs/DELETEs to /api/* are made from the same page via fetch()
        // without a CSRF token. Skipping CSRF on /api/* keeps the inline UI honest
        // (real apps would mount an api.php route file and not hit the web group).
        $middleware->validateCsrfTokens(except: ['api/*']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Why: surface SDK errors as their underlying HTTP status (e.g. 404 for
        // a missing document) instead of Laravel's default 500. Mirrors the
        // global error mapping shipped by the Next.js / NestJS / FastAPI demos.
        $exceptions->render(function (PoliPageException $e): JsonResponse {
            $status = $e->status >= 400 ? $e->status : 500;

            return new JsonResponse([
                'error' => [
                    'code' => $e->errorCode,
                    'message' => $e->getMessage(),
                    'status' => $e->status,
                    'requestId' => $e->requestId,
                ],
            ], $status);
        });
    })
    ->create();

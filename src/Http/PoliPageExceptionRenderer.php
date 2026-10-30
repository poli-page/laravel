<?php

declare(strict_types=1);

namespace PoliPage\Laravel\Http;

use Illuminate\Http\JsonResponse;
use PoliPage\PoliPageException;

/**
 * Turns an uncaught PoliPageException into the HTTP answer every Poli Page
 * integration gives: status `toPayload()['status']` (500 when the SDK knows
 * none) and the body `toPayload()` exactly: `{ code, message, status, requestId }`.
 *
 * The service provider registers it on Laravel's exception handler unless
 * `poli-page.error_handler` is false.
 */
final class PoliPageExceptionRenderer
{
    public function __invoke(PoliPageException $exception): JsonResponse
    {
        $payload = $exception->toPayload();

        return new JsonResponse($payload, $payload['status'] ?? JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
    }
}

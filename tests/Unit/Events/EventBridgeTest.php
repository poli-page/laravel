<?php

declare(strict_types=1);

namespace PoliPage\Laravel\Tests\Unit\Events;

use Closure;
use Illuminate\Support\Facades\Event;
use PoliPage\Events\RetryEvent;
use PoliPage\Laravel\Events\PoliPageErrored;
use PoliPage\Laravel\Events\PoliPageRetrying;
use PoliPage\Laravel\Tests\TestCase;
use PoliPage\PoliPage;
use PoliPage\PoliPageException;
use ReflectionClass;

final class EventBridgeTest extends TestCase
{
    public function test_sdk_retry_hook_dispatches_poli_page_retrying_event(): void
    {
        $app = $this->app;
        \assert($app !== null);

        Event::fake([PoliPageRetrying::class]);

        $client = $app->make(PoliPage::class);
        $onRetry = (new ReflectionClass($client))->getProperty('onRetry')->getValue($client);
        self::assertInstanceOf(Closure::class, $onRetry);

        $sdkEvent = new RetryEvent(2, 250.0, new PoliPageException('boom', PoliPageException::INTERNAL_ERROR));
        $onRetry($sdkEvent);

        Event::assertDispatched(
            PoliPageRetrying::class,
            fn (PoliPageRetrying $e): bool => $e->sdkEvent === $sdkEvent,
        );
    }

    public function test_sdk_error_hook_dispatches_poli_page_errored_event(): void
    {
        $app = $this->app;
        \assert($app !== null);

        Event::fake([PoliPageErrored::class]);

        $client = $app->make(PoliPage::class);
        $onError = (new ReflectionClass($client))->getProperty('onError')->getValue($client);
        self::assertInstanceOf(Closure::class, $onError);

        $exception = new PoliPageException('terminal', PoliPageException::INTERNAL_ERROR);
        $onError($exception);

        Event::assertDispatched(
            PoliPageErrored::class,
            fn (PoliPageErrored $e): bool => $e->exception === $exception,
        );
    }
}

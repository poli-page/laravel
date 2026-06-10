<?php

declare(strict_types=1);

namespace PoliPage\Laravel\Tests\Unit;

use Illuminate\Contracts\Config\Repository;
use PoliPage\Laravel\Facades\PoliPage as PoliPageFacade;
use PoliPage\Laravel\Tests\TestCase;
use PoliPage\PoliPage;
use ReflectionClass;

final class PoliPageServiceProviderTest extends TestCase
{
    public function test_poli_page_resolves_as_singleton(): void
    {
        $app = $this->app;
        \assert($app !== null);

        $a = $app->make(PoliPage::class);
        $b = $app->make(PoliPage::class);

        self::assertInstanceOf(PoliPage::class, $a);
        self::assertSame($a, $b, 'PoliPage must be bound as a singleton (same instance across resolutions).');
    }

    public function test_facade_resolves_to_the_same_instance(): void
    {
        $app = $this->app;
        \assert($app !== null);

        $direct = $app->make(PoliPage::class);
        $viaFacade = PoliPageFacade::getFacadeRoot();

        self::assertSame($direct, $viaFacade);
    }

    public function test_config_reaches_constructor(): void
    {
        $app = $this->app;
        \assert($app !== null);

        $config = $app->make(Repository::class);
        $config->set('poli-page.api_key', 'pp_test_custom_key');
        $config->set('poli-page.base_url', 'https://api.example.com');
        $config->set('poli-page.timeout', 42.0);
        $config->set('poli-page.retries.max_attempts', 5);
        $config->set('poli-page.retries.delay_seconds', 0.1);

        // Singleton was already resolved with the default config in defineEnvironment().
        // Re-resolve with the overridden config by forgetting the binding.
        $app->forgetInstance(PoliPage::class);

        $client = $app->make(PoliPage::class);

        $reflection = new ReflectionClass($client);
        self::assertSame('pp_test_custom_key', $reflection->getProperty('apiKey')->getValue($client));
        self::assertSame('https://api.example.com', $reflection->getProperty('baseUrl')->getValue($client));
        self::assertSame(42.0, $reflection->getProperty('defaultTimeout')->getValue($client));
        self::assertSame(5, $reflection->getProperty('maxRetries')->getValue($client));
        self::assertSame(0.1, $reflection->getProperty('retryDelay')->getValue($client));
    }
}

<?php

declare(strict_types=1);

namespace PoliPage\Laravel\Tests\Unit;

use Illuminate\Contracts\Config\Repository;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PoliPage\Laravel\Tests\TestCase;
use PoliPage\PoliPage;

final class ConfigValidationTest extends TestCase
{
    public function test_valid_config_constructs_client(): void
    {
        $app = $this->app;
        \assert($app !== null);

        $app->make(Repository::class)->set('poli-page', [
            'api_key' => 'pp_test_valid',
            'base_url' => 'https://api-develop.poli.page',
            'timeout' => 30.0,
            'retries' => ['max_attempts' => 3, 'delay_seconds' => 0.25],
        ]);
        $app->forgetInstance(PoliPage::class);

        self::assertInstanceOf(PoliPage::class, $app->make(PoliPage::class));
    }

    public function test_missing_api_key_throws(): void
    {
        $app = $this->app;
        \assert($app !== null);

        $app->make(Repository::class)->set('poli-page.api_key', null);
        $app->forgetInstance(PoliPage::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/api_key.*required/i');
        $app->make(PoliPage::class);
    }

    /** @return iterable<string, array{0: string}> */
    public static function invalidApiKeyProvider(): iterable
    {
        yield 'no prefix' => ['abcdef'];
        yield 'wrong prefix' => ['sk_test_abc'];
        yield 'just pp_' => ['pp_abc'];
        yield 'pp_prod_' => ['pp_prod_abc'];
    }

    #[DataProvider('invalidApiKeyProvider')]
    public function test_invalid_api_key_prefix_throws(string $key): void
    {
        $app = $this->app;
        \assert($app !== null);

        $app->make(Repository::class)->set('poli-page.api_key', $key);
        $app->forgetInstance(PoliPage::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/pp_test_ or pp_live_/');
        $app->make(PoliPage::class);
    }

    /** @return iterable<string, array{0: float|int}> */
    public static function invalidTimeoutProvider(): iterable
    {
        yield 'zero' => [0];
        yield 'negative' => [-1];
        yield 'too large' => [601];
    }

    #[DataProvider('invalidTimeoutProvider')]
    public function test_invalid_timeout_throws(float|int $value): void
    {
        $app = $this->app;
        \assert($app !== null);

        $config = $app->make(Repository::class);
        $config->set('poli-page.api_key', 'pp_test_x');
        $config->set('poli-page.timeout', $value);
        $app->forgetInstance(PoliPage::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/timeout/');
        $app->make(PoliPage::class);
    }

    public function test_retries_max_attempts_out_of_range(): void
    {
        $app = $this->app;
        \assert($app !== null);

        $config = $app->make(Repository::class);
        $config->set('poli-page.api_key', 'pp_test_x');
        $config->set('poli-page.retries.max_attempts', 11);
        $app->forgetInstance(PoliPage::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/max_attempts/');
        $app->make(PoliPage::class);
    }

    public function test_base_url_must_be_http_scheme(): void
    {
        $app = $this->app;
        \assert($app !== null);

        $config = $app->make(Repository::class);
        $config->set('poli-page.api_key', 'pp_test_x');
        $config->set('poli-page.base_url', 'ftp://api.poli.page');
        $app->forgetInstance(PoliPage::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/http/');
        $app->make(PoliPage::class);
    }
}

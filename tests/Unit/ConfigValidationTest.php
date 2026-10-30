<?php

declare(strict_types=1);

namespace PoliPage\Laravel\Tests\Unit;

use Illuminate\Contracts\Config\Repository;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PoliPage\Laravel\Tests\RecordingLogger;
use PoliPage\Laravel\Tests\TestCase;
use PoliPage\PoliPage;
use Psr\Log\LoggerInterface;

final class ConfigValidationTest extends TestCase
{
    public function test_valid_config_constructs_client(): void
    {
        $app = $this->app;
        \assert($app !== null);

        $app->make(Repository::class)->set('poli-page', [
            'api_key' => self::SANDBOX_API_KEY,
            'base_url' => 'https://api.example.com',
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

    public function test_empty_api_key_throws(): void
    {
        $app = $this->app;
        \assert($app !== null);

        $app->make(Repository::class)->set('poli-page.api_key', '');
        $app->forgetInstance(PoliPage::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/api_key.*required/i');
        $app->make(PoliPage::class);
    }

    /** @return iterable<string, array{0: string}> */
    public static function wellFormedApiKeyProvider(): iterable
    {
        yield 'sandbox' => [self::SANDBOX_API_KEY];
        yield 'live' => ['pp_live_0123456789abcdef0123456789abcdef'];
        yield 'hyphenated environment' => ['pp_eu-prod_0123456789abcdef0123456789abcdef'];
        yield '20-character environment' => ['pp_abcdefghijklmnopqrst_0123456789abcdef0123456789abcdef'];
    }

    #[DataProvider('wellFormedApiKeyProvider')]
    public function test_well_formed_api_key_boots_without_warning(string $key): void
    {
        $logger = $this->bootWithKey($key);

        self::assertSame([], $logger->warnings);
    }

    /** @return iterable<string, array{0: string, 1: string}> */
    public static function malformedApiKeyProvider(): iterable
    {
        $hex = '0123456789abcdef0123456789abcdef';

        yield 'short secret' => ['pp_sandbox_x', 'pp_sandbox_x'];
        yield 'uppercase environment' => ["pp_Sandbox_{$hex}", $hex];
        yield 'environment starting with a digit' => ["pp_2fa_{$hex}", $hex];
        yield 'double hyphen' => ["pp_a--b_{$hex}", $hex];
        yield '21-character environment' => ["pp_abcdefghijklmnopqrstu_{$hex}", $hex];
        yield 'not a pp_ token' => ['sk_live_secretvalue', 'secretvalue'];
        yield 'trailing newline' => ["pp_sandbox_{$hex}\n", $hex];
    }

    #[DataProvider('malformedApiKeyProvider')]
    public function test_malformed_api_key_boots_with_one_warning_without_the_secret(string $key, string $secret): void
    {
        $logger = $this->bootWithKey($key);

        self::assertCount(1, $logger->warnings);
        self::assertStringNotContainsString($secret, $logger->warnings[0]);
        self::assertStringNotContainsString($key, $logger->warnings[0]);
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
        $config->set('poli-page.api_key', self::SANDBOX_API_KEY);
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
        $config->set('poli-page.api_key', self::SANDBOX_API_KEY);
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
        $config->set('poli-page.api_key', self::SANDBOX_API_KEY);
        $config->set('poli-page.base_url', 'ftp://api.poli.page');
        $app->forgetInstance(PoliPage::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/http/');
        $app->make(PoliPage::class);
    }

    private function bootWithKey(string $key): RecordingLogger
    {
        $app = $this->app;
        \assert($app !== null);

        $logger = new RecordingLogger;
        $app->instance(LoggerInterface::class, $logger);
        $app->make(Repository::class)->set('poli-page.api_key', $key);
        $app->forgetInstance(PoliPage::class);

        self::assertInstanceOf(PoliPage::class, $app->make(PoliPage::class));
        $app->make(PoliPage::class);

        return $logger;
    }
}

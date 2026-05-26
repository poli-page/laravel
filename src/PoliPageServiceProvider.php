<?php

declare(strict_types=1);

namespace PoliPage\Laravel;

use Closure;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use PoliPage\Events\RetryEvent;
use PoliPage\Laravel\Events\PoliPageErrored;
use PoliPage\Laravel\Events\PoliPageRetrying;
use PoliPage\Laravel\Http\PoliPageResponseFactory;
use PoliPage\PoliPage;
use PoliPage\PoliPageException;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;

final class PoliPageServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/poli-page.php', 'poli-page');

        $this->app->singleton(PoliPageResponseFactory::class);

        $this->app->singleton(PoliPage::class, function (Application $app): PoliPage {
            /** @var array<string, mixed> $config */
            $config = $app->make(ConfigRepository::class)->get('poli-page', []);
            self::validate($config);

            /** @var array<string, mixed> $retries */
            $retries = is_array($config['retries'] ?? null) ? $config['retries'] : [];

            /** @var ClientInterface|null $httpClient */
            $httpClient = self::resolveOptional($app, $config['http_client'] ?? null, ClientInterface::class);
            /** @var RequestFactoryInterface|null $requestFactory */
            $requestFactory = self::resolveOptional($app, $config['request_factory'] ?? null, RequestFactoryInterface::class);
            /** @var StreamFactoryInterface|null $streamFactory */
            $streamFactory = self::resolveOptional($app, $config['stream_factory'] ?? null, StreamFactoryInterface::class);
            /** @var LoggerInterface|null $logger */
            $logger = self::resolveOptional($app, $config['logger'] ?? null, LoggerInterface::class);

            $dispatcher = $app->make(Dispatcher::class);
            $onRetry = self::buildRetryHook($app, $dispatcher, $config['on_retry'] ?? null);
            $onError = self::buildErrorHook($app, $dispatcher, $config['on_error'] ?? null);

            return new PoliPage(
                apiKey: (string) $config['api_key'],
                baseUrl: self::asNullableString($config['base_url'] ?? null),
                maxRetries: self::asNullableInt($retries['max_attempts'] ?? null),
                retryDelay: self::asNullableFloat($retries['delay_seconds'] ?? null),
                timeout: self::asNullableFloat($config['timeout'] ?? null),
                httpClient: $httpClient,
                requestFactory: $requestFactory,
                streamFactory: $streamFactory,
                logger: $logger,
                onRetry: $onRetry,
                onError: $onError,
            );
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/poli-page.php' => $this->app->configPath('poli-page.php'),
        ], 'poli-page-config');
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private static function validate(array $config): void
    {
        $apiKey = $config['api_key'] ?? null;
        if (! is_string($apiKey) || $apiKey === '') {
            throw new InvalidArgumentException(
                'Poli Page api_key is required. Set POLI_PAGE_API_KEY in your .env file.',
            );
        }
        if (preg_match('/^pp_(test|live)_/', $apiKey) !== 1) {
            throw new InvalidArgumentException(
                'Poli Page API key must start with pp_test_ or pp_live_. '
                .'Get one at https://app.poli.page/settings/api-keys.',
            );
        }

        $timeout = $config['timeout'] ?? null;
        if ($timeout !== null) {
            $timeout = (float) $timeout;
            if ($timeout <= 0 || $timeout > 600) {
                throw new InvalidArgumentException(
                    "Poli Page timeout must be > 0 and <= 600 seconds. Got: {$timeout}.",
                );
            }
        }

        /** @var array<string, mixed> $retries */
        $retries = is_array($config['retries'] ?? null) ? $config['retries'] : [];

        $maxAttempts = $retries['max_attempts'] ?? null;
        if ($maxAttempts !== null) {
            $maxAttempts = (int) $maxAttempts;
            if ($maxAttempts < 0 || $maxAttempts > 10) {
                throw new InvalidArgumentException(
                    "Poli Page retries.max_attempts must be between 0 and 10. Got: {$maxAttempts}.",
                );
            }
        }

        $delaySeconds = $retries['delay_seconds'] ?? null;
        if ($delaySeconds !== null) {
            $delaySeconds = (float) $delaySeconds;
            if ($delaySeconds < 0 || $delaySeconds > 30) {
                throw new InvalidArgumentException(
                    "Poli Page retries.delay_seconds must be between 0 and 30 seconds. Got: {$delaySeconds}.",
                );
            }
        }

        $baseUrl = $config['base_url'] ?? null;
        if ($baseUrl !== null) {
            $scheme = parse_url((string) $baseUrl, PHP_URL_SCHEME);
            if (! in_array($scheme, ['http', 'https'], true)) {
                throw new InvalidArgumentException(
                    "Poli Page base_url must use http or https scheme. Got: {$baseUrl}.",
                );
            }
        }
    }

    private static function buildRetryHook(Application $app, Dispatcher $dispatcher, mixed $userBinding): Closure
    {
        if ($userBinding !== null) {
            return self::resolveUserClosure($app, $userBinding, 'on_retry');
        }

        return static function (RetryEvent $event) use ($dispatcher): void {
            $dispatcher->dispatch(new PoliPageRetrying($event));
        };
    }

    private static function buildErrorHook(Application $app, Dispatcher $dispatcher, mixed $userBinding): Closure
    {
        if ($userBinding !== null) {
            return self::resolveUserClosure($app, $userBinding, 'on_error');
        }

        return static function (PoliPageException $exception) use ($dispatcher): void {
            $dispatcher->dispatch(new PoliPageErrored($exception));
        };
    }

    private static function resolveUserClosure(Application $app, mixed $binding, string $configKey): Closure
    {
        if (! is_string($binding)) {
            throw new InvalidArgumentException(
                "Poli Page config '{$configKey}' must be a container binding (string). Got: ".get_debug_type($binding),
            );
        }
        $resolved = $app->make($binding);
        if (! is_callable($resolved)) {
            throw new InvalidArgumentException(
                "Poli Page config '{$configKey}': container binding '{$binding}' must resolve to a callable. Got: ".get_debug_type($resolved),
            );
        }

        return Closure::fromCallable($resolved);
    }

    /**
     * @template T of object
     *
     * @param  class-string<T>  $expected
     * @return T|null
     */
    private static function resolveOptional(Application $app, mixed $binding, string $expected): ?object
    {
        if ($binding === null) {
            return null;
        }
        if (! is_string($binding)) {
            throw new InvalidArgumentException(
                "Poli Page config binding for {$expected} must be a container key (string). Got: ".get_debug_type($binding),
            );
        }
        $resolved = $app->make($binding);
        if (! $resolved instanceof $expected) {
            throw new InvalidArgumentException(
                "Poli Page config: container binding '{$binding}' must resolve to {$expected}. Got: ".get_debug_type($resolved),
            );
        }

        return $resolved;
    }

    private static function asNullableString(mixed $value): ?string
    {
        return $value === null ? null : (string) $value;
    }

    private static function asNullableInt(mixed $value): ?int
    {
        return $value === null ? null : (int) $value;
    }

    private static function asNullableFloat(mixed $value): ?float
    {
        return $value === null ? null : (float) $value;
    }
}

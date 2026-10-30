<?php

declare(strict_types=1);

namespace PoliPage\Laravel;

use Closure;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use PoliPage\Events\RetryEvent;
use PoliPage\Laravel\Console\RenderCommand;
use PoliPage\Laravel\Events\PoliPageErrored;
use PoliPage\Laravel\Events\PoliPageRetrying;
use PoliPage\Laravel\Http\PoliPageExceptionRenderer;
use PoliPage\Laravel\Http\PoliPageResponseFactory;
use PoliPage\PoliPage;
use PoliPage\PoliPageException;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;

final class PoliPageServiceProvider extends ServiceProvider
{
    /**
     * Full-key regex of the SDK contract: pp_{environment}_{32 lowercase hex}.
     * Anchored with \z, not $: PHP's $ also matches before a final "\n".
     */
    private const API_KEY_PATTERN = '/^pp_(?=[a-z][a-z0-9-]{0,19}_)[a-z][a-z0-9]*(?:-[a-z0-9]+)*_[0-9a-f]{32}\z/';

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/poli-page.php', 'poli-page');

        $this->app->singleton(PoliPageResponseFactory::class);

        $this->app->singleton(PoliPage::class, function (Application $app): PoliPage {
            /** @var array<string, mixed> $config */
            $config = $app->make(ConfigRepository::class)->get('poli-page', []);
            self::validate($config);
            self::warnIfMalformedApiKey($app, (string) $config['api_key']);

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

        if ($this->app->runningInConsole()) {
            $this->commands([RenderCommand::class]);
        }

        if ((bool) $this->app->make(ConfigRepository::class)->get('poli-page.error_handler', true)) {
            $this->registerErrorHandler();
        }
    }

    /**
     * Why: an uncaught SDK error otherwise reaches the app as a generic 500; this
     * answers its own status and `toPayload()` body, the same in every integration.
     */
    private function registerErrorHandler(): void
    {
        $this->callAfterResolving(ExceptionHandler::class, static function (ExceptionHandler $handler): void {
            if (! $handler instanceof Handler) {
                return;
            }
            $renderer = new PoliPageExceptionRenderer;
            $handler->renderable(static fn (PoliPageException $e): JsonResponse => $renderer($e));
        });
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

    /**
     * Why warn instead of throw: the API is the only authority on keys (it answers
     * 401 INVALID_API_KEY); a key the regex rejects may still be one it accepts.
     * The message never echoes the key, so the secret cannot leak into logs.
     */
    private static function warnIfMalformedApiKey(Application $app, string $apiKey): void
    {
        if (preg_match(self::API_KEY_PATTERN, $apiKey) === 1) {
            return;
        }

        $app->make(LoggerInterface::class)->warning(
            'Poli Page API key does not match the expected format pp_{environment}_{32 lowercase hex characters}. '
            .'Starting anyway; the API will reject it if it is invalid. '
            .'Get one at https://app.poli.page/settings/api-keys.',
        );
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

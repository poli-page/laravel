<?php

declare(strict_types=1);

namespace PoliPage\Laravel;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use PoliPage\PoliPage;

final class PoliPageServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/poli-page.php', 'poli-page');

        $this->app->singleton(PoliPage::class, function (Application $app): PoliPage {
            /** @var array<string, mixed> $config */
            $config = $app->make(ConfigRepository::class)->get('poli-page', []);

            /** @var array<string, mixed> $retries */
            $retries = is_array($config['retries'] ?? null) ? $config['retries'] : [];

            return new PoliPage(
                apiKey: (string) ($config['api_key'] ?? ''),
                baseUrl: self::asNullableString($config['base_url'] ?? null),
                maxRetries: self::asNullableInt($retries['max_attempts'] ?? null),
                retryDelay: self::asNullableFloat($retries['delay_seconds'] ?? null),
                timeout: self::asNullableFloat($config['timeout'] ?? null),
                // PSR providers / logger / hooks land in Task 5 + Task 7.
            );
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/poli-page.php' => $this->app->configPath('poli-page.php'),
        ], 'poli-page-config');
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

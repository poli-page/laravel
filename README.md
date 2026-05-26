# Poli Page Laravel Package

[![CI](https://github.com/poli-page/laravel/actions/workflows/ci.yml/badge.svg)](https://github.com/poli-page/laravel/actions/workflows/ci.yml)
[![Latest Stable Version](https://img.shields.io/packagist/v/poli-page/laravel.svg)](https://packagist.org/packages/poli-page/laravel)
[![License](https://img.shields.io/packagist/l/poli-page/laravel.svg)](LICENSE)

Official Laravel package for [Poli Page](https://poli.page) — render polished PDFs from HTML templates via the Poli Page API. Wraps the [official PHP SDK](https://packagist.org/packages/poli-page/sdk) with a Laravel-native ServiceProvider, Facade, response helpers, Artisan command, and event bridges.

→ API reference (auto-generated from source): **https://docs.poli.page/reference/sdk/php/**

## Requirements

- PHP 8.3+
- Laravel 10.x LTS or 11.x

## Install

```bash
composer require poli-page/laravel
```

Package auto-discovery does the rest: the ServiceProvider registers, the `PoliPage` Facade alias is wired. Add your API key to `.env`:

```
POLI_PAGE_API_KEY=pp_test_your_key_here
```

Done. Inject `\PoliPage\PoliPage` or call `PoliPage::render()->pdf(...)` from anywhere.

To customise the config, publish it:

```bash
php artisan vendor:publish --tag=poli-page-config
```

## Get an API key

Sign up at [app.poli.page](https://app.poli.page) (or [app-develop.poli.page](https://app-develop.poli.page) for develop), then **Settings → API Keys** → create a `pp_test_*` key.

## Quick start — render a PDF from a controller

```php
use PoliPage\PoliPage;
use PoliPage\ProjectModeInput;
use PoliPage\Laravel\Http\PoliPageResponseFactory;

class InvoiceController
{
    public function __construct(
        private readonly PoliPage $poliPage,
        private readonly PoliPageResponseFactory $factory,
    ) {}

    public function show(string $id)
    {
        $pdf = $this->poliPage->render->pdf(new ProjectModeInput(
            project: 'invoices',
            template: 'default',
            data: ['invoice_id' => $id],
            version: '1.0.0',
        ));

        return $this->factory->bytes($pdf, "invoice-{$id}.pdf");
    }
}
```

`$factory->bytes(...)` sets the right `Content-Type`, RFC 5987 `Content-Disposition`, `Cache-Control: private, no-store`, and `X-Content-Type-Options: nosniff` — the parts you'd otherwise get wrong.

Same thing through the Facade:

```php
use PoliPage\Laravel\Facades\PoliPage;

$pdf = PoliPage::render()->pdf(new ProjectModeInput(/* ... */));
```

## Smoke-test your config from the CLI

```bash
php artisan poli-page:render \
    --project=getting-started \
    --template=welcome \
    --template-version=1.0.0 \
    --data='{"name":"World"}' \
    -o welcome.pdf
```

`--template-version`, not `--version` — Artisan reserves `--version` globally.

## Full configuration

```php
// config/poli-page.php (after vendor:publish)
return [
    'api_key' => env('POLI_PAGE_API_KEY'),
    'base_url' => env('POLI_PAGE_BASE_URL'),
    'timeout' => env('POLI_PAGE_TIMEOUT'),
    'user_agent' => env('POLI_PAGE_USER_AGENT'),
    'retries' => [
        'max_attempts' => env('POLI_PAGE_RETRY_MAX_ATTEMPTS'),
        'delay_seconds' => env('POLI_PAGE_RETRY_DELAY_SECONDS'),
    ],
    'http_client' => null,
    'request_factory' => null,
    'stream_factory' => null,
    'logger' => null,
    'on_retry' => null,
    'on_error' => null,
];
```

Every optional key is `null` by default; the SDK applies its own defaults. Override individual env vars to customise.

## Event bridges

The SDK fires `onRetry` / `onError` Closure hooks. The package bridges those into Laravel events you subscribe to like any other:

```php
use PoliPage\Laravel\Events\PoliPageRetrying;
use Illuminate\Support\Facades\Log;

class LogPoliPageRetries
{
    public function handle(PoliPageRetrying $event): void
    {
        Log::warning('Poli Page retry', [
            'attempt'  => $event->sdkEvent->attempt,
            'delay_ms' => $event->sdkEvent->delayMs,
            'reason'   => $event->sdkEvent->reason->getMessage(),
        ]);
    }
}
```

Register via Laravel 11's event auto-discovery (just create the listener) or via `EventServiceProvider::$listen` on Laravel 10.

## Try the example app

A full runnable Laravel 11 app showing every public method of the SDK is in `example-app/`. Visit `http://localhost:8000/` after `php artisan serve` for an interactive single-page dashboard. See `example-app/README.md` for details.

## Errors

Everything thrown is a `PoliPage\PoliPageException` (or a subclass). The package does not catch or transform exceptions; let them propagate or handle them in your controllers / listeners / exception handler.

## Contributing

See [`CLAUDE.md`](CLAUDE.md). PRs welcome — please open an issue first for anything beyond a small fix.

## License

[MIT](LICENSE).

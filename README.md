# Poli Page for Laravel

> Render Poli Page documents as Laravel HTTP responses.

[![CI](https://github.com/poli-page/laravel/actions/workflows/ci.yml/badge.svg)](https://github.com/poli-page/laravel/actions/workflows/ci.yml)
[![Latest Stable Version](https://img.shields.io/packagist/v/poli-page/laravel.svg)](https://packagist.org/packages/poli-page/laravel)
[![License](https://img.shields.io/packagist/l/poli-page/laravel.svg)](LICENSE)

## About

This package wraps the Poli Page PHP SDK as a Laravel-native integration: a service container singleton, a `PoliPage` Facade, a `PoliPageResponseFactory` for controller responses with the right PDF headers, an Artisan smoke-test command, and event bridges that forward the SDK's retry and error hooks onto Laravel's event dispatcher. You configure it through `config/poli-page.php` and return rendered PDFs the same way you return JSON or a Blade view.

**When to use this:**

- You want to return a generated PDF from a controller action with correct `Content-Type`, `Content-Disposition`, and cache headers.
- You want the Poli Page client autowired from the container and configured through `.env`.
- You want SDK retry and error events to flow through Laravel listeners alongside the rest of your app's events.

**When not to:**

- You need to generate PDFs without a remote API — Poli Page is a hosted service.
- You're working outside Laravel — pick the matching Poli Page package for your stack ([Symfony](https://packagist.org/packages/poli-page/symfony-bundle), Rails, Django, FastAPI, Next.js, NestJS, Rocket).

## Requirements

- PHP 8.3+
- Laravel 10.x LTS or 11.x
- A Poli Page API key from [app.poli.page](https://app.poli.page)

## Install

```bash
composer require poli-page/laravel
```

Package auto-discovery registers `PoliPageServiceProvider` and the `PoliPage` Facade alias. Add your API key to `.env`:

```
POLI_PAGE_API_KEY=pp_test_your_key_here
```

Publish the config file when you need to override defaults:

```bash
php artisan vendor:publish --tag=poli-page-config
```

Verify the integration end-to-end from the CLI:

```bash
php artisan poli-page:render \
    --project=getting-started \
    --template=welcome \
    --template-version=1.0.0 \
    --data='{"name":"World"}' \
    -o welcome.pdf
```

## Quick start

Inject the client and the response factory into a controller, then return the rendered bytes.

```php
// app/Http/Controllers/InvoiceController.php
namespace App\Http\Controllers;

use PoliPage\Laravel\Http\PoliPageResponseFactory;
use PoliPage\PoliPage;
use PoliPage\ProjectModeInput;

class InvoiceController
{
    public function __construct(
        private readonly PoliPage $poliPage,
        private readonly PoliPageResponseFactory $responses,
    ) {}

    public function show(string $id)
    {
        $pdf = $this->poliPage->render->pdf(new ProjectModeInput(
            project: 'invoices',
            template: 'default',
            version: '1.0.0',
            data: ['invoice_id' => $id],
        ));

        return $this->responses->bytes($pdf, "invoice-{$id}.pdf");
    }
}
```

## Configuration

| Option | Default | Description |
|---|---|---|
| `api_key` | `env('POLI_PAGE_API_KEY')` | Your Poli Page API key. Must start with `pp_test_` or `pp_live_`. |
| `base_url` | SDK default | API endpoint. Override for staging or self-hosted. |
| `timeout` | SDK default | Per-request timeout in seconds. Must be between `0` and `600`. |
| `user_agent` | SDK default | Custom `User-Agent` header. |
| `retries.max_attempts` | SDK default | Automatic retries on 429 / 5xx. Must be between `0` and `10`. |
| `retries.delay_seconds` | SDK default | Base delay between retries. Must be between `0` and `30`. |
| `http_client` | `null` | Container key of a PSR-18 `ClientInterface` binding. |
| `request_factory` | `null` | Container key of a PSR-17 `RequestFactoryInterface` binding. |
| `stream_factory` | `null` | Container key of a PSR-17 `StreamFactoryInterface` binding. |
| `logger` | `null` | Container key of a PSR-3 `LoggerInterface` binding. |
| `on_retry` | `null` | Container key of a callable. When set, replaces the default `PoliPageRetrying` event bridge. |
| `on_error` | `null` | Container key of a callable. When set, replaces the default `PoliPageErrored` event bridge. |

Every optional key is `null` by default and the SDK applies its own defaults. Override individual values through env vars or by editing the published config:

```php
// config/poli-page.php
return [
    'api_key' => env('POLI_PAGE_API_KEY'),
    'base_url' => env('POLI_PAGE_BASE_URL'),
    'timeout' => env('POLI_PAGE_TIMEOUT'),
    'retries' => [
        'max_attempts' => env('POLI_PAGE_RETRY_MAX_ATTEMPTS'),
        'delay_seconds' => env('POLI_PAGE_RETRY_DELAY_SECONDS'),
    ],
];
```

## API at a glance

| Symbol | Purpose |
|---|---|
| `PoliPage\PoliPage` | SDK client singleton. Inject by FQCN; exposes `render` and `documents`. |
| `PoliPage\Laravel\Facades\PoliPage` | Facade alias. Use `PoliPage::render()->pdf(...)` outside constructors. |
| `PoliPage\Laravel\Http\PoliPageResponseFactory` | Builds `Response` / `StreamedResponse` / `RedirectResponse` with PDF, preview, and document-redirect headers. |
| `PoliPage\Laravel\Events\PoliPageRetrying` | Laravel event dispatched for each SDK retry attempt. Wraps the SDK's `RetryEvent`. |
| `PoliPage\Laravel\Events\PoliPageErrored` | Laravel event dispatched on terminal SDK errors. Wraps the underlying `PoliPageException`. |
| `php artisan poli-page:render` | End-to-end smoke-test command. PDF or HTML preview output. |

Full reference: [docs/api.md](docs/api.md).

## Errors

The SDK throws four families of exceptions. They all extend `PoliPage\PoliPageException`; this package does not catch or transform them.

- **Auth** — `PoliPage\Exception\AuthenticationException`. Invalid or missing API key (HTTP 401).
- **Rate limit** — `PoliPage\Exception\RateLimitException`. Rate limit exceeded (HTTP 429); honor `Retry-After`.
- **Request rejected** — `PoliPage\Exception\BadRequestException`. Template or data rejected by the API (HTTP 400).
- **Network / transport** — `PoliPage\Exception\ConnectionException`. Network or transport failure after the SDK's retry budget is exhausted.

```php
use PoliPage\Exception\AuthenticationException;
use PoliPage\Exception\BadRequestException;
use PoliPage\Exception\ConnectionException;
use PoliPage\Exception\RateLimitException;
use PoliPage\PoliPageException;

try {
    $pdf = $this->poliPage->render->pdf($input);
} catch (AuthenticationException $e) {
    abort(500, 'Poli Page credentials rejected.');
} catch (RateLimitException $e) {
    return response('Try again shortly.', 503);
} catch (BadRequestException $e) {
    return back()->withErrors(['template' => $e->getMessage()]);
} catch (ConnectionException $e) {
    report($e);
    abort(502);
} catch (PoliPageException $e) {
    report($e);
    abort(500);
}
```

## Example app

A runnable Laravel 11 app at [`example-app/`](example-app/) demonstrates every public SDK method through routes, an interactive single-page dashboard at `GET /`, and an `app:demo:render-to-file` command.

```bash
cd example-app
composer install
php artisan serve
```

## Going further

- Event bridges — listening to `PoliPageRetrying` and `PoliPageErrored` from Laravel listeners (forthcoming `docs/events.md`).
- Streaming large PDFs — using `PoliPageResponseFactory::stream()` for multi-megabyte documents (forthcoming `docs/streaming.md`).
- Stored documents — persisting a `document_id` and serving presigned URLs through `documentRedirect()` (forthcoming `docs/storage.md`).
- Testing — swapping the singleton with `PoliPage::swap($fake)` or `$this->app->instance()` (forthcoming `docs/testing.md`).

## Compatibility

| Package | Laravel | PHP |
|---|---|---|
| 0.1.x | 10.x LTS / 11.x | 8.3 / 8.4 |

The package follows Laravel's own supported-versions window and tracks the latest stable PHP minor.

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md).

## License

Released under the [MIT License](LICENSE).

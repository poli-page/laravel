# Poli Page for Laravel

[![Packagist](https://img.shields.io/packagist/v/poli-page/laravel?style=flat&logo=php&logoColor=ffffff&label=Packagist)](https://packagist.org/packages/poli-page/laravel)
[![Downloads](https://img.shields.io/packagist/dt/poli-page/laravel?style=flat&logo=php&logoColor=ffffff&label=Downloads)](https://packagist.org/packages/poli-page/laravel)
[![Ci](https://img.shields.io/github/actions/workflow/status/poli-page/laravel/ci.yml?branch=main&style=flat&logo=githubactions&logoColor=ffffff&label=Ci)](https://github.com/poli-page/laravel/actions/workflows/ci.yml)
[![Codeql](https://img.shields.io/github/actions/workflow/status/poli-page/laravel/codeql.yml?branch=main&style=flat&logo=github&logoColor=ffffff&label=Codeql)](https://github.com/poli-page/laravel/actions/workflows/codeql.yml)
[![Coverage](https://img.shields.io/codecov/c/github/poli-page/laravel?style=flat&logo=codecov&logoColor=ffffff&label=Coverage)](https://codecov.io/gh/poli-page/laravel)
[![Php](https://img.shields.io/packagist/php-v/poli-page/laravel?style=flat&logo=php&logoColor=ffffff&label=Php)](https://packagist.org/packages/poli-page/laravel)
[![Laravel](https://img.shields.io/badge/Laravel-12%20%7C%2013-blue?style=flat&logo=laravel&logoColor=ffffff)](https://laravel.com/)
[![Phpstan](https://img.shields.io/badge/Phpstan-8-blue?style=flat&logo=php&logoColor=ffffff)](phpstan.neon)
[![Deps](https://img.shields.io/badge/Deps-up%20to%20date-brightgreen?style=flat&logo=php&logoColor=ffffff)](https://github.com/poli-page/laravel/network/dependencies)
[![Docs](https://img.shields.io/badge/Docs-online-brightgreen?style=flat&logo=readthedocs&logoColor=ffffff)](https://poli-page.github.io/laravel/)
[![License](https://img.shields.io/badge/License-MIT-blue?style=flat&logo=gnu&logoColor=ffffff)](LICENSE)

> Render Poli Page documents as Laravel HTTP responses.

→ **Documentation**: https://poli-page.github.io/laravel/

## About

This package wraps the Poli Page PHP SDK as a Laravel-native integration: a service container singleton, a `PoliPage` Facade, a `PoliPageResponseFactory` for controller responses with the right PDF headers, an Artisan smoke-test command, and event bridges that forward the SDK's retry and error hooks onto Laravel's event dispatcher. You configure it through `config/poli-page.php` and return rendered PDFs the same way you return JSON or a Blade view.

**When to use this:**

- You want to return a generated PDF from a controller action with correct `Content-Type`, `Content-Disposition`, and cache headers.
- You want the Poli Page client autowired from the container and configured through `.env`.
- You want SDK retry and error events to flow through Laravel listeners alongside the rest of your app's events.

**When not to:**

- You need to generate PDFs without a remote API — Poli Page is a hosted service.
- You're working outside Laravel — pick the matching Poli Page package for your stack ([Symfony](https://packagist.org/packages/poli-page/symfony), Rails, Django, FastAPI, Next.js, NestJS, Rocket).

## Requirements

- PHP 8.3+
- Laravel 12.x or 13.x
- [`poli-page/sdk`](https://packagist.org/packages/poli-page/sdk) 0.13.x (Composer installs it with the package)
- A Poli Page API key from [app.poli.page](https://app.poli.page)

## Install

```bash
composer require poli-page/laravel
```

Package auto-discovery registers `PoliPageServiceProvider` and the `PoliPage` Facade alias. Add your API key to `.env`:

```
POLI_PAGE_API_KEY=pp_sandbox_x
```

Each API key belongs to one environment of your organization and has the form `pp_{environment}_{32 lowercase hex characters}`. Start with your `sandbox` key:

| Environment                           | Key            | Watermark | Counted |
| ------------------------------------- | -------------- | --------- | ------- |
| `sandbox` (every org)                 | `pp_sandbox_…` | yes       | no      |
| `live` (every org, deletable on Pro+) | `pp_live_…`    | no        | yes     |
| created on Pro+ (e.g. `staging`)      | `pp_staging_…` | no        | yes     |

"Counted" means the render counts toward your organization's monthly PDF quota. The key picks the environment, and each render uses the copy of the project published in that environment, so there is no template version to pass.

The package only requires the key to be set. A key that does not match `^pp_(?=[a-z][a-z0-9-]{0,19}_)[a-z][a-z0-9]*(?:-[a-z0-9]+)*_[0-9a-f]{32}\z` logs one warning through Laravel's logger when the client is first resolved (the key itself is never logged), and the API answers `401` if it is invalid.

Publish the config file when you need to override defaults:

```bash
php artisan vendor:publish --tag=poli-page-config
```

Verify the integration end-to-end from the CLI with a `sandbox` key; every organization has the `getting-started` project's `welcome` template in its `sandbox` environment:

```bash
php artisan poli-page:render \
    --project=getting-started \
    --template=welcome \
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
use PoliPage\RenderInput;

class InvoiceController
{
    public function __construct(
        private readonly PoliPage $poliPage,
        private readonly PoliPageResponseFactory $responses,
    ) {}

    public function show(string $id)
    {
        $pdf = $this->poliPage->render->pdf(new RenderInput(
            project: 'invoices',
            template: 'default',
            data: ['invoice_id' => $id],
        ));

        return $this->responses->bytes($pdf, "invoice-{$id}.pdf");
    }
}
```

## Configuration

| Option | Default | Description |
|---|---|---|
| `api_key` | `env('POLI_PAGE_API_KEY')` | Your Poli Page API key, `pp_{environment}_{32 hex}`. Required; a malformed value logs one warning and the API decides. |
| `base_url` | SDK default | API endpoint. Override for staging or self-hosted. |
| `timeout` | SDK default | Timeout in seconds of each API call and of the PDF download (for `pdfStream()`, the longest wait for the next chunk). Must be between `0` and `600`. |
| `retries.max_attempts` | SDK default | Automatic retries on 429 / 5xx. Must be between `0` and `10`. |
| `retries.delay_seconds` | SDK default | Base delay between retries. Must be between `0` and `30`. |
| `http_client` | `null` | Container key of a PSR-18 `ClientInterface` binding. |
| `request_factory` | `null` | Container key of a PSR-17 `RequestFactoryInterface` binding. |
| `stream_factory` | `null` | Container key of a PSR-17 `StreamFactoryInterface` binding. |
| `logger` | `null` | Container key of a PSR-3 `LoggerInterface` binding. |
| `on_retry` | `null` | Container key of a callable. When set, replaces the default `PoliPageRetrying` event bridge. |
| `on_error` | `null` | Container key of a callable. When set, replaces the default `PoliPageErrored` event bridge. |
| `error_handler` | `env('POLI_PAGE_ERROR_HANDLER', true)` | Answers an uncaught `PoliPageException` with its `toPayload()` JSON body and status (see [Errors](#errors)). Set to `false` to let your app handle it. |

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
| `PoliPage\Laravel\Http\PoliPageResponseFactory` | Builds `Response` / `StreamedResponse` / `RedirectResponse` with PDF, stored-document HTML preview (`documents->preview()`), and document-redirect headers. |
| `PoliPage\Laravel\Events\PoliPageRetrying` | Laravel event dispatched for each SDK retry attempt. Wraps the SDK's `RetryEvent`. |
| `PoliPage\Laravel\Events\PoliPageErrored` | Laravel event dispatched on terminal SDK errors. Wraps the underlying `PoliPageException`. |
| `php artisan poli-page:render` | End-to-end smoke-test command. Renders a template to a PDF file. |

Full reference: [poli-page.github.io/laravel](https://poli-page.github.io/laravel/reference/api/).

## Errors

### Error handler

The package registers an error handler on Laravel's exception handler, on by default. A `PoliPage\PoliPageException` that your code does not catch becomes a JSON answer built from the SDK's `toPayload()`:

```json
{ "code": "TEMPLATE_NOT_FOUND", "message": "Template 'does-not-exist' not found in project 'getting-started'.", "status": 404, "requestId": "req_…" }
```

- The HTTP status is the payload's `status`: the API's status (404, 429, 500, …), `503` for a `network_error`, `504` for a `timeout` and `502` for an `invalid_response` (a 2xx whose body is not JSON, usually from a proxy between your app and the API). An SDK error with no status (for example `invalid_options`, or `io_error` from `renderToFile()`) answers `500`, with `"status": null` in the body.
- `requestId` is the API's `x-request-id`, or `null` when there is none.
- The `Content-Type` is `application/json`. Laravel still reports the exception to your log as usual.
- For a streamed PDF, call `render->pdfStream()` in the controller and pass its stream to `PoliPageResponseFactory::stream()`, which reads the first chunk before it returns. A render or download error then reaches the handler before any PDF header is sent. Calling the SDK inside your own `StreamedResponse` callback would commit a `200 application/pdf` first.

To handle SDK errors yourself, turn it off in `.env`:

```
POLI_PAGE_ERROR_HANDLER=false
```

or with `'error_handler' => false` in `config/poli-page.php`. A `render()` callback you register for `PoliPageException` in `bootstrap/app.php` also takes precedence when it returns a response.

### Exception types

The SDK throws five families of exceptions. They all extend `PoliPage\PoliPageException`. Catch them where you want a different answer than the error handler's:

- **Auth** — `PoliPage\Exception\AuthenticationException`. Invalid or missing API key (HTTP 401).
- **Rate limit** — `PoliPage\Exception\RateLimitException`. Rate limit exceeded (HTTP 429); honor `Retry-After`.
- **Request rejected** — `PoliPage\Exception\BadRequestException`. Template or data rejected by the API (HTTP 400).
- **Network / transport** — `PoliPage\Exception\ConnectionException`. Network or transport failure after the SDK's retry budget is exhausted.
- **Invalid response** — `PoliPage\Exception\InvalidResponseException`. A 2xx whose body is not JSON, usually from a proxy or captive portal; never retried.

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

A runnable Laravel 13 app at [`example-app/`](example-app/) demonstrates every public SDK method through routes (`GET /render/pdf`, `GET /documents/{id}`, `GET /errors/not-found`, …), an interactive single-page dashboard at `GET /`, and an `app:demo:render-to-file` command.

```bash
cd example-app
composer install
php artisan serve
```

## Going further

- [Event bridges](https://poli-page.github.io/laravel/production/observability/): listening to `PoliPageRetrying` and `PoliPageErrored` from Laravel listeners.
- [Streaming large PDFs](https://poli-page.github.io/laravel/concepts/responses/#streamed-pdf): using `PoliPageResponseFactory::stream()` for multi-megabyte documents.
- [Stored documents](https://poli-page.github.io/laravel/concepts/responses/#redirect-to-a-stored-document): serving presigned URLs through `documentRedirect()`.
- [Testing](https://poli-page.github.io/laravel/production/testing/): replacing the client with a fake HTTP client.

## Compatibility

| Package | `poli-page/sdk` | Laravel | PHP |
|---|---|---|---|
| 0.13.x | 0.13.x | 12.x / 13.x | 8.3 / 8.4 / 8.5 |

The package shares its major.minor with the Poli Page API and requires the SDK of the same minor (see [RELEASING.md](RELEASING.md#versions)). It follows Laravel's own supported-versions window and tracks the latest stable PHP minor.

## Contributing

```bash
composer install
composer ci            # Pint, PHPStan and the unit tests
```

### Contract tests

`tests/Contract/ImplContractTest.php` runs the integration cases of the Poli Page API contract (`impl.*` in [poli-page/api-contract](https://github.com/poli-page/api-contract)) against the example app, with its SDK pointed at a local mock API. CI runs them against the contract version pinned in `.github/workflows/ci.yml`. Locally:

```bash
POLI_CONTRACT=/path/to/contract.json vendor/bin/phpunit --testsuite=contract
```

Without `POLI_CONTRACT` the suite is skipped (it fails when `CI` is set). A case that must fail for now goes in `tests/contract-skip.json` with its reason.

## License

Released under the [MIT License](LICENSE).

# Changelog

All notable changes to `poli-page/laravel` are documented here. Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/); the project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.13.1] - 2026-11-05

### Added
- `retries.max_retries` (env `POLI_PAGE_RETRY_MAX_RETRIES`): the number of retries after the first attempt (0 to 10), passed to the SDK as `maxRetries`.

### Deprecated
- `retries.max_attempts` (env `POLI_PAGE_RETRY_MAX_ATTEMPTS`) is renamed `retries.max_retries`. It never counted attempts: it was the SDK's `maxRetries`, so `3` made up to 4 attempts. The old key keeps its meaning and works, with an `E_USER_DEPRECATED` notice; setting both keys to different values throws `InvalidArgumentException`.

## [0.13.0] - 2026-11-02

First release on Packagist. It requires `poli-page/sdk` 0.13 and passes the Poli Page API contract v0.13.0. The package shares its major.minor with the Poli Page API, so its first version is 0.13.0; a 0.11.0 was prepared and never published, and neither was the 0.1.0 development line. The Changed, Removed and Fixed entries below describe changes made during those lines.

### Added
- `PoliPageServiceProvider` with singleton container binding, config validation, publishable `config/poli-page.php`.
- `PoliPage` Facade exposing `render()` / `documents()` methods over the SDK's readonly properties.
- `PoliPageResponseFactory` with `bytes` / `stream` / `preview` / `documentRedirect` builders (correct PDF/HTML headers, RFC 5987 filename encoding, `Cache-Control: private, no-store`).
- `php artisan poli-page:render` command for end-to-end smoke testing.
- Laravel event bridges for the SDK's `onRetry` / `onError` Closure hooks (`PoliPageRetrying`, `PoliPageErrored`).
- Package auto-discovery via `composer.json` `extra.laravel`.
- Example Laravel 13 app at `example-app/` with interactive single-page demo UI at `GET /` covering the SDK demo steps.
- Error handler, on by default: an uncaught `PoliPageException` answers its `toPayload()` body, `{ "code", "message", "status", "requestId" }`, as `application/json` with the payload's status (`503` for `network_error`, `504` for `timeout`, `502` for `invalid_response`, `500` when the SDK knows no status, as for `invalid_options` and `io_error`). Turn it off with `POLI_PAGE_ERROR_HANDLER=false` or `'error_handler' => false` in `config/poli-page.php`.
- Contract tests: CI runs the integration cases of the Poli Page API contract v0.13.0 (`poli-page/api-contract`), including `impl.invalid-response`, against the example app on every matrix leg, and builds the example app.
- Dependabot for Composer (the package and the example app) and GitHub Actions; the API contract has its own group and no cooldown.
- Release workflow: the tag's major.minor must be the pinned API contract's (a stable contract tag on the pinned commit, not the comment next to it) and the required `poli-page/sdk`'s (`scripts/check-release-versions.sh`); the error names both versions.

### Changed
- Requires `poli-page/sdk` `^0.13.0` (`>=0.13.0 <0.14`). With it, a 2xx from the API whose body is not JSON (usually from a proxy) raises `InvalidResponseException`, code `invalid_response` (was `INTERNAL_ERROR`), which the error handler answers `502`; a `renderToFile()` that cannot write its file raises `io_error` (was `invalid_options`), answered `500` with `"status": null`; and the PDF download is bounded by the `timeout` option (an idle timeout for `pdfStream()`).
- The live API CI job passes the optional `POLI_PAGE_BASE_URL` secret to the integration test (unset: the SDK default), next to the `pp_sandbox_` key in `POLI_PAGE_API_KEY`. The integration test sets `poli-page.base_url` from `POLI_PAGE_BASE_URL` when it is set.
- CI: the live API job runs on `main` (and on demand) against the production API with the sandbox key from the `POLI_PAGE_API_KEY` secret, and skips with a notice when the key is absent or not a `pp_sandbox_` key.
- API keys follow the environments format `pp_{environment}_{32 lowercase hex}` (`pp_sandbox_…`, `pp_live_…`, `pp_staging_…`). The service provider no longer rejects keys outside the old test/live prefixes: it still throws on a missing or empty `api_key`, and logs one warning through Laravel's logger (without the key) when the key does not match the SDK's full-key regex, then lets the API decide.
- Renders follow the SDK's environment keys and have no template versions: they take a `RenderInput` with no `version`; the key's environment selects the published copy of the project.
- `PoliPageResponseFactory::preview()` takes a `DocumentPreviewResult` only (the stored HTML of a rendered document).
- Example app: the routes follow the SDK demo contract and lose their `/api` prefix: `GET /render/pdf`, `GET /documents/{id}` (a `302` to the presigned PDF URL), `GET /errors/not-found` and the others. The error-handling demo `GET /errors/not-found` renders `getting-started` with template `does-not-exist` and lets the package's error handler answer `404` with code `TEMPLATE_NOT_FOUND`. Demo steps are renumbered 1–9.
- Example app: reads `POLI_PAGE_API_KEY` from the environment or `example-app/.env`.
- The integration test runs only with a `pp_sandbox_` key.
- Supported versions are now Laravel 12.x (Orchestra Testbench 10) and Laravel 13.x (Orchestra Testbench 11) on PHP 8.3–8.5. Laravel 10.x and 11.x are dropped: every release of both is flagged by Packagist security advisories, so Composer refuses to install them. Dev tooling moves to PHPUnit 11.5/12.5, PHPStan 2 and Larastan 3.

### Removed
- `php artisan poli-page:render`: the `--template-version`, `--preview` and `--html` options. The command renders a PDF from `--project` and `--template`.
- Example app: `GET /api/render/preview` and its dashboard button, and `GET /api/errors/bad-version`.

### Fixed
- `PoliPageResponseFactory::stream()` reads the first chunk before it returns, so a download that fails on its first read (`DOWNLOAD_FAILED`, `network_error`) reaches the error handler and answers its JSON payload, instead of a `200` with PDF headers and no body.
- Example app: `bootstrap/cache` and `storage/framework/views` are in the repository, so `php artisan` and the demo page work in a fresh clone.
- `PoliPageResponseFactory::bytes()` / `stream()`: control characters (CR/LF, TAB, DEL, C1) are now stripped from the `Content-Disposition` filename instead of surviving as `?` in the fallback and `%0D%0A` in `filename*`. Filenames containing `/`, `\` or `%` no longer throw `InvalidArgumentException` from `HeaderUtils::makeDisposition()` (a 500): path separators become `_`, and `%` becomes `?` in the ASCII fallback only. The fallback now has one `?` per non-ASCII character (was one per UTF-8 byte), and a filename made only of control characters falls back to `document.pdf`.
- A key ending in a newline now logs the malformed-key warning (the format check is anchored with `\z`, not `$`).
- `php artisan poli-page:render` no longer prints the configured API key in its error output. A key ending in a newline made PSR-7 reject the `Authorization` header with the whole `Bearer <key>` value in the exception message, which the command printed as is. Guzzle trims spaces and tabs from the value first, so the trimmed key is redacted too.

[Unreleased]: https://github.com/poli-page/laravel/compare/v0.13.1...HEAD
[0.13.1]: https://github.com/poli-page/laravel/compare/v0.13.0...v0.13.1
[0.13.0]: https://github.com/poli-page/laravel/releases/tag/v0.13.0

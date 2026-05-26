# Changelog

All notable changes to `poli-page/laravel` are documented here. Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/); the project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Initial release scaffolding.

## [0.1.0] — TBD

### Added
- `PoliPageServiceProvider` with singleton container binding, config validation, publishable `config/poli-page.php`.
- `PoliPage` Facade exposing `render()` / `documents()` methods over the SDK's readonly properties.
- `PoliPageResponseFactory` with `bytes` / `stream` / `preview` / `documentRedirect` builders (correct PDF/HTML headers, RFC 5987 filename encoding, `Cache-Control: private, no-store`).
- `php artisan poli-page:render` command for end-to-end smoke testing.
- Laravel event bridges for the SDK's `onRetry` / `onError` Closure hooks (`PoliPageRetrying`, `PoliPageErrored`).
- Package auto-discovery via `composer.json` `extra.laravel`.
- Example Laravel 11 app at `example-app/` with interactive single-page demo UI at `GET /` covering all 10 SDK demo steps.

[Unreleased]: https://github.com/poli-page/laravel/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/poli-page/laravel/releases/tag/v0.1.0

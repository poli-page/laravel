# Changelog

All notable changes to `poli-page/laravel` are documented here. Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/); the project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Initial release scaffolding.

### Changed
- Supported versions are now Laravel 12.x (Orchestra Testbench 10) and Laravel 13.x (Orchestra Testbench 11) on PHP 8.3–8.5. Laravel 10.x and 11.x are dropped: every release of both is flagged by Packagist security advisories, so Composer refuses to install them. Dev tooling moves to PHPUnit 11.5/12.5, PHPStan 2 and Larastan 3.

### Fixed
- `PoliPageResponseFactory::bytes()` / `stream()`: control characters (CR/LF, TAB, DEL, C1) are now stripped from the `Content-Disposition` filename instead of surviving as `?` in the fallback and `%0D%0A` in `filename*`. Filenames containing `/`, `\` or `%` no longer throw `InvalidArgumentException` from `HeaderUtils::makeDisposition()` (a 500): path separators become `_`, and `%` becomes `?` in the ASCII fallback only. The fallback now has one `?` per non-ASCII character (was one per UTF-8 byte), and a filename made only of control characters falls back to `document.pdf`.

## [0.1.0] — TBD

### Added
- `PoliPageServiceProvider` with singleton container binding, config validation, publishable `config/poli-page.php`.
- `PoliPage` Facade exposing `render()` / `documents()` methods over the SDK's readonly properties.
- `PoliPageResponseFactory` with `bytes` / `stream` / `preview` / `documentRedirect` builders (correct PDF/HTML headers, RFC 5987 filename encoding, `Cache-Control: private, no-store`).
- `php artisan poli-page:render` command for end-to-end smoke testing.
- Laravel event bridges for the SDK's `onRetry` / `onError` Closure hooks (`PoliPageRetrying`, `PoliPageErrored`).
- Package auto-discovery via `composer.json` `extra.laravel`.
- Example Laravel 13 app at `example-app/` with interactive single-page demo UI at `GET /` covering all 10 SDK demo steps.

[Unreleased]: https://github.com/poli-page/laravel/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/poli-page/laravel/releases/tag/v0.1.0

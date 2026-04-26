# poli-page/laravel

Laravel integration for [Poli Page](https://poli.page) — generate PDFs from controllers, jobs, and notifications with a Service Provider, Facade, and config file.

> **Status**: scaffold only. Implementation begins in P2.2 of the [SDK roadmap](https://github.com/poli-page/poli-page/blob/develop/docs/onboarding/micka/sdk-roadmap.md).

## Install

```bash
composer require poli-page/laravel
php artisan vendor:publish --tag=poli-page-config
```

## Quick start

To be filled in as the integration is built. The package will register a Service Provider with auto-discovery, expose a `PoliPage` Facade, and bind the client to Laravel's container with config from `config/poli-page.php`.

## Dependencies

This package depends on [`poli-page/sdk`](https://github.com/poli-page/sdk-php) (the core PHP SDK). It is declared in `composer.json` and installed automatically. All HTTP, retry, and error-handling logic lives in the core SDK — this repo only adds Laravel glue.

## Publishing

Published to **Packagist** as [`poli-page/laravel`](https://packagist.org/packages/poli-page/laravel).

## Documentation

Full Poli Page documentation is at [docs.poli.page](https://docs.poli.page).

## License

MIT — see [LICENSE](./LICENSE).

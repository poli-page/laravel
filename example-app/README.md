# `poli-page/laravel` example app

Minimal Laravel 13 app demonstrating every public method of the Poli Page PHP SDK through the Laravel package. Each route or command corresponds 1:1 to a step in the SDK's canonical demo ([`examples/demo.php`](https://github.com/poli-page/sdk-php/blob/main/examples/demo.php)). An interactive single-page demo UI lives at `GET /`.

## Setup

```bash
cd example-app
composer install
php artisan serve
```

The example app reads `POLI_PAGE_API_KEY` (a `pp_sandbox_…` key) and, optionally, `POLI_PAGE_BASE_URL` from the environment or from `example-app/.env` (Laravel loads it; it is gitignored). The demo renders the `getting-started` project's `welcome` template, which every organization has in its `sandbox` environment. If your organization renamed, deleted or unpublished that project, the demo answers `404 PROJECT_NOT_FOUND` or `404 PROJECT_NOT_IN_ENVIRONMENT`: use a fresh organization.

```bash
echo 'POLI_PAGE_API_KEY=pp_sandbox_…' > .env
```

## The interactive demo

Open `http://localhost:8000/` in your browser. Single-page dashboard: one button per SDK feature, inline PDF preview via `<iframe>`, the stored document's HTML via `<iframe srcdoc>`, JSON results pretty-printed, document lifecycle state machine in JS.

## JSON routes (mirror SDK demo steps 1, 2, 4–9)

| SDK demo step | URL | What it does |
|---|---|---|
| 1. `render->pdf()` | `GET /render/pdf` | Returns the welcome PDF as `application/pdf`. |
| 2. `render->pdfStream()` | `GET /render/stream` | Same PDF but via PSR-7 stream + `StreamedResponse`. |
| 4. `render->document()` | `POST /documents` | Stores the document, returns descriptor JSON. |
| 5. `documents->get(id)` | `GET /documents/{id}` | 302 to the presigned PDF URL. |
| 6. `documents->thumbnails(id)` | `GET /documents/{id}/thumbnails` | Page thumbnails as base64 JSON. |
| 7. `documents->preview(id)` | `GET /documents/{id}/preview` | Stored document's HTML preview. |
| 8. `documents->delete(id)` | `DELETE /documents/{id}` | Soft-delete, `204 No Content`. |
| 9. Error handling | `GET /errors/not-found` | Renders `getting-started` with template `does-not-exist`; answers `404` with code `TEMPLATE_NOT_FOUND` and the error's `code`, `status`, `message` and `requestId`. |

## Commands

| SDK demo step | Command |
|---|---|
| 3. `renderToFile()` | `php artisan app:demo:render-to-file` |
| (package smoke test) | `php artisan poli-page:render --project=getting-started --template=welcome` |

## Quick smoke

```bash
curl -o welcome.pdf http://localhost:8000/render/pdf
open welcome.pdf
```

If you see a styled welcome PDF, the package + SDK + your API key all work end-to-end.

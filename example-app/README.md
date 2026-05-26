# `poli-page/laravel` example app

Minimal Laravel 11 app demonstrating every public method of the Poli Page PHP SDK through the Laravel package. Each route or command corresponds 1:1 to a step in the SDK's canonical demo (`../../sdk-php.md/examples/demo.php`). An interactive single-page demo UI lives at `GET /`.

## Setup

```bash
cd example-app
composer install
php artisan serve
```

The example app reads its `POLI_PAGE_API_KEY` from the workspace root `.env` (`../../symfony-bundle/.env`) automatically. **No `cp .env .env.local` step required.** Set the key once in that shared file and every integration's example-app picks it up.

## The interactive demo

Open `http://localhost:8000/` in your browser. Single-page dashboard: one button per SDK feature, inline PDF preview via `<iframe>`, inline HTML preview via `<iframe srcdoc>`, JSON results pretty-printed, document lifecycle state machine in JS.

## JSON routes (mirror SDK demo steps 1, 2, 4–10)

| SDK demo step | URL | What it does |
|---|---|---|
| 1. `render->pdf()` | `GET /api/render/pdf` | Returns the welcome PDF as `application/pdf`. |
| 2. `render->pdfStream()` | `GET /api/render/stream` | Same PDF but via PSR-7 stream + `StreamedResponse`. |
| 4. `render->preview()` | `GET /api/render/preview[?html=...]` | HTML preview. Pass `?html=<raw>` for inline mode. |
| 5. `render->document()` | `POST /api/documents` | Stores the document, returns descriptor JSON. |
| 6. `documents->get(id)` | `GET /api/documents/{id}` | 302 to the presigned PDF URL. |
| 7. `documents->thumbnails(id)` | `GET /api/documents/{id}/thumbnails` | Page thumbnails as base64 JSON. |
| 8. `documents->preview(id)` | `GET /api/documents/{id}/preview` | Stored document's HTML preview. |
| 9. `documents->delete(id)` | `DELETE /api/documents/{id}` | Soft-delete, `204 No Content`. |
| 10. Error handling | `GET /api/errors/bad-version` | Deliberately triggers `INVALID_VERSION_FORMAT`. |

## Commands

| SDK demo step | Command |
|---|---|
| 3. `renderToFile()` | `php artisan app:demo:render-to-file` |
| (package smoke test) | `php artisan poli-page:render --project=getting-started --template=welcome --template-version=1.0.0` |

## Quick smoke

```bash
curl -o welcome.pdf http://localhost:8000/api/render/pdf
open welcome.pdf
```

If you see a styled welcome PDF, the package + SDK + your API key all work end-to-end.

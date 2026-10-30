<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use PoliPage\Laravel\Facades\PoliPage;
use PoliPage\Laravel\Http\PoliPageResponseFactory;
use PoliPage\RenderInput;
use PoliPage\ThumbnailOptions;

final class DocumentController
{
    public function __construct(private readonly PoliPageResponseFactory $factory) {}

    /** Demo step 5: documents->get(id) — fresh descriptor + 302 to presigned URL. */
    public function get(string $id): RedirectResponse
    {
        $descriptor = PoliPage::documents()->get($id);

        return $this->factory->documentRedirect($descriptor);
    }

    /** Demo step 6: documents->thumbnails(id, opts). */
    public function thumbnails(string $id): JsonResponse
    {
        $thumbnails = PoliPage::documents()->thumbnails($id, new ThumbnailOptions(width: 240));

        return new JsonResponse([
            'count' => count($thumbnails),
            'thumbnails' => array_map(static fn ($t): array => [
                'page' => $t->page,
                'width' => $t->width,
                'height' => $t->height,
                'contentType' => $t->contentType,
                'base64Bytes' => $t->data,
            ], $thumbnails),
        ]);
    }

    /** Demo step 7: documents->preview(id). */
    public function preview(string $id): Response
    {
        $result = PoliPage::documents()->preview($id);

        return $this->factory->preview($result);
    }

    /** Demo step 8: documents->delete(id). */
    public function delete(string $id): Response
    {
        PoliPage::documents()->delete($id);

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    /**
     * Demo step 9: error handling. Renders a template that does not exist; the
     * API answers 404 TEMPLATE_NOT_FOUND and the package's error handler turns
     * the uncaught PoliPageException into its toPayload() body with status 404.
     */
    public function notFound(): Response
    {
        $pdf = PoliPage::render()->pdf(new RenderInput(
            project: 'getting-started',
            template: 'does-not-exist',
            data: [],
        ));

        return $this->factory->bytes($pdf, 'does-not-exist.pdf');
    }
}

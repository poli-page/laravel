<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use PoliPage\Laravel\Facades\PoliPage;
use PoliPage\Laravel\Http\PoliPageResponseFactory;
use PoliPage\PoliPageException;
use PoliPage\ProjectModeInput;
use PoliPage\ThumbnailOptions;

final class DocumentController
{
    public function __construct(private readonly PoliPageResponseFactory $factory) {}

    /** Demo step 6: documents->get(id) — fresh descriptor + 302 to presigned URL. */
    public function get(string $id): RedirectResponse
    {
        $descriptor = PoliPage::documents()->get($id);

        return $this->factory->documentRedirect($descriptor);
    }

    /** Demo step 7: documents->thumbnails(id, opts). */
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

    /** Demo step 8: documents->preview(id). */
    public function preview(string $id): Response
    {
        $result = PoliPage::documents()->preview($id);

        return $this->factory->preview($result);
    }

    /** Demo step 9: documents->delete(id). */
    public function delete(string $id): Response
    {
        PoliPage::documents()->delete($id);

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    /** Demo step 10: error handling — deliberately trigger INVALID_VERSION_FORMAT. */
    public function badVersion(): JsonResponse
    {
        try {
            PoliPage::render()->pdf(new ProjectModeInput(
                project: 'getting-started',
                template: 'welcome',
                data: [],
                version: 'not-semver',
            ));
        } catch (PoliPageException $e) {
            $payload = $e->toPayload();
            $status = $payload['status'] ?? 500;
            return new JsonResponse([
                'caught' => true,
                'code' => $payload['code'],
                'message' => $payload['message'],
                'status' => $status,
                'requestId' => $payload['requestId'],
            ], $status);
        }

        return new JsonResponse(['caught' => false, 'note' => 'expected an exception, got success'], 500);
    }
}

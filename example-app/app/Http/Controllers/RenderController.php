<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use PoliPage\Laravel\Http\PoliPageResponseFactory;
use PoliPage\PoliPage;
use PoliPage\RenderInput;
use Symfony\Component\HttpFoundation\StreamedResponse;

use function PoliPage\renderToFile;

final class RenderController
{
    public function __construct(
        private readonly PoliPage $poliPage,
        private readonly PoliPageResponseFactory $factory,
    ) {}

    /** Demo step 1: render->pdf() — fetch PDF bytes into memory. */
    public function pdf(): Response
    {
        $pdf = $this->poliPage->render->pdf(new RenderInput(
            project: 'getting-started',
            template: 'welcome',
            data: ['name' => 'Laravel'],
        ));

        return $this->factory->bytes($pdf, 'welcome.pdf');
    }

    /** Demo step 2: render->pdfStream() — PSR-7 stream of PDF bytes. */
    public function stream(): StreamedResponse
    {
        $stream = $this->poliPage->render->pdfStream(new RenderInput(
            project: 'getting-started',
            template: 'welcome',
            data: ['name' => 'Laravel streamed'],
        ));

        return $this->factory->stream($stream, 'welcome-streamed.pdf');
    }

    /** Demo step 3: renderToFile() — stream the PDF straight to disk, memory-bounded. */
    public function renderFile(): JsonResponse
    {
        $output = storage_path('poli-page/welcome.pdf');
        if (! is_dir(\dirname($output))) {
            mkdir(\dirname($output), 0o775, true);
        }

        renderToFile($this->poliPage, new RenderInput(
            project: 'getting-started',
            template: 'welcome',
            data: ['name' => 'renderToFile demo'],
        ), $output);

        return new JsonResponse([
            'path' => $output,
            'sizeBytes' => filesize($output) ?: 0,
        ]);
    }

    /** Demo step 4: render->document() — store the document, return descriptor JSON. */
    public function createDocument(): JsonResponse
    {
        $descriptor = $this->poliPage->render->document(new RenderInput(
            project: 'getting-started',
            template: 'welcome',
            data: ['name' => 'Stored doc'],
        ));

        return new JsonResponse([
            'documentId' => $descriptor->documentId,
            'pageCount' => $descriptor->pageCount,
            'sizeBytes' => $descriptor->sizeBytes,
            'environment' => $descriptor->environment,
            'expiresAt' => $descriptor->expiresAt,
            'presignedPdfUrl' => $descriptor->presignedPdfUrl,
        ]);
    }
}

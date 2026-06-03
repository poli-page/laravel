<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use PoliPage\InlineModeInput;
use PoliPage\Laravel\Http\PoliPageResponseFactory;
use PoliPage\PoliPage;
use PoliPage\ProjectModeInput;
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
        $pdf = $this->poliPage->render->pdf(new ProjectModeInput(
            project: 'getting-started',
            template: 'welcome',
            data: ['name' => 'Laravel'],
            version: '1.0.0',
        ));

        return $this->factory->bytes($pdf, 'welcome.pdf');
    }

    /** Demo step 2: render->pdfStream() — PSR-7 stream of PDF bytes. */
    public function stream(): StreamedResponse
    {
        $stream = $this->poliPage->render->pdfStream(new ProjectModeInput(
            project: 'getting-started',
            template: 'welcome',
            data: ['name' => 'Laravel streamed'],
            version: '1.0.0',
        ));

        return $this->factory->stream($stream, 'welcome-streamed.pdf');
    }

    /** Demo step 4: render->preview() — paginated HTML preview. */
    public function preview(Request $request): Response
    {
        $html = $request->query('html');
        $input = is_string($html) && $html !== ''
            ? new InlineModeInput(template: $html, data: ['name' => 'Inline'])
            : new ProjectModeInput(
                project: 'getting-started',
                template: 'welcome',
                data: ['name' => 'Preview from project'],
                version: '1.0.0',
            );

        $result = $this->poliPage->render->preview($input);

        return $this->factory->preview($result);
    }

    /** Demo step 3: renderToFile() — stream the PDF straight to disk, memory-bounded. */
    public function renderFile(): JsonResponse
    {
        $output = storage_path('poli-page/welcome.pdf');
        if (! is_dir(\dirname($output))) {
            mkdir(\dirname($output), 0o775, true);
        }

        renderToFile($this->poliPage, new ProjectModeInput(
            project: 'getting-started',
            template: 'welcome',
            data: ['name' => 'renderToFile demo'],
            version: '1.0.0',
        ), $output);

        return new JsonResponse([
            'path' => $output,
            'sizeBytes' => filesize($output) ?: 0,
        ]);
    }

    /** Demo step 5: render->document() — store the document, return descriptor JSON. */
    public function createDocument(): JsonResponse
    {
        $descriptor = $this->poliPage->render->document(new ProjectModeInput(
            project: 'getting-started',
            template: 'welcome',
            data: ['name' => 'Stored doc'],
            version: '1.0.0',
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

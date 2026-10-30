<?php

declare(strict_types=1);

namespace PoliPage\Laravel\Tests\Unit\Http;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Routing\Router;
use PHPUnit\Framework\Attributes\DataProvider;
use PoliPage\Laravel\Http\PoliPageResponseFactory;
use PoliPage\Laravel\Tests\Contract\MockServer;
use PoliPage\Laravel\Tests\TestCase;
use PoliPage\PoliPage;
use PoliPage\RenderInput;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A streamed-PDF route, wired like the example app's GET /render/stream, with
 * the real SDK against a local mock API: every SDK error (the render, or the
 * start of the download) reaches the error handler before a PDF header is sent.
 */
final class StreamRouteTest extends TestCase
{
    /**
     * @param  Router  $router
     */
    protected function defineRoutes($router): void
    {
        $router->get('/stream', static function (PoliPage $client, PoliPageResponseFactory $factory): StreamedResponse {
            $stream = $client->render->pdfStream(new RenderInput(
                project: 'getting-started',
                template: 'welcome',
                data: ['name' => 'Stream'],
            ));

            return $factory->stream($stream, 'welcome.pdf');
        });
    }

    /**
     * @return iterable<string, array{string, int, array{code: string, message: string, status: int|null, requestId: string|null}}>
     */
    public static function failures(): iterable
    {
        $rendered = '{"status": 200, "json": {"documentId": "doc_1", "presignedPdfUrl": "{{mock}}/storage/doc_1.pdf",'
            .' "environment": "sandbox", "pageCount": 1, "sizeBytes": 10, "expiresAt": "2026-09-29T10:00:22.000Z",'
            .' "createdAt": "2026-09-29T09:45:22.000Z", "format": "A4", "orientation": "portrait", "locale": "en-US",'
            .' "organizationId": "org_1", "projectId": "p", "projectSlug": "getting-started", "templateId": "t",'
            .' "templateSlug": "welcome", "apiKeyId": "k", "metadata": {}, "diagnostics": []}}';

        yield 'render 404' => [
            '[{"status": 404, "headers": {"x-request-id": "req_404"}, "json": {"error": "TEMPLATE_NOT_FOUND", "detail": "Template not found.", "requestId": "req_404"}}]',
            404,
            ['code' => 'TEMPLATE_NOT_FOUND', 'message' => 'Template not found.', 'status' => 404, 'requestId' => 'req_404'],
        ];
        yield 'render network error' => [
            '[{"fault": "drop"}]',
            503,
            ['code' => 'network_error', 'message' => 'Network request failed', 'status' => 503, 'requestId' => null],
        ];
        yield 'download network error' => [
            '['.$rendered.', {"fault": "drop"}]',
            500,
            ['code' => 'DOWNLOAD_FAILED', 'message' => 'Failed to download PDF: network error', 'status' => null, 'requestId' => null],
        ];
        yield 'download 403' => [
            '['.$rendered.', {"status": 403, "text": "AccessDenied"}]',
            403,
            ['code' => 'DOWNLOAD_FAILED', 'message' => 'Failed to download PDF: HTTP 403', 'status' => 403, 'requestId' => null],
        ];
    }

    /**
     * @param  array{code: string, message: string, status: int|null, requestId: string|null}  $payload
     */
    #[DataProvider('failures')]
    public function test_an_sdk_error_answers_the_handler_json_without_pdf_headers(string $steps, int $status, array $payload): void
    {
        $mock = $this->pointSdkAt($steps);
        try {
            $response = $this->get('/stream');
        } finally {
            $mock->stop();
        }

        $response->assertStatus($status);
        self::assertSame('application/json', $response->headers->get('Content-Type'));
        self::assertNull($response->headers->get('Content-Disposition'));
        self::assertSame($payload, $response->json());
    }

    public function test_a_render_streams_the_pdf(): void
    {
        $mock = $this->pointSdkAt('[{"status": 200, "json": {"documentId": "doc_1", "presignedPdfUrl": "{{mock}}/storage/doc_1.pdf",'
            .' "environment": "sandbox", "pageCount": 1, "sizeBytes": 10, "expiresAt": "2026-09-29T10:00:22.000Z",'
            .' "createdAt": "2026-09-29T09:45:22.000Z", "format": "A4", "orientation": "portrait", "locale": "en-US",'
            .' "organizationId": "org_1", "projectId": "p", "projectSlug": "getting-started", "templateId": "t",'
            .' "templateSlug": "welcome", "apiKeyId": "k", "metadata": {}, "diagnostics": []}},'
            .' {"status": 200, "headers": {"content-type": "application/pdf"}, "text": "%PDF-1.4 bytes"}]');
        try {
            $response = $this->get('/stream');
            $body = $response->streamedContent();
        } finally {
            $mock->stop();
        }

        $response->assertStatus(200);
        self::assertSame('application/pdf', $response->headers->get('Content-Type'));
        self::assertSame('%PDF-1.4 bytes', $body);
    }

    private function pointSdkAt(string $steps): MockServer
    {
        $decoded = json_decode($steps, false, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);
        $mock = new MockServer(array_values($decoded));
        $app = $this->app;
        \assert($app !== null);
        $config = $app->make(Repository::class);
        $config->set('poli-page.base_url', $mock->origin);
        // One attempt per call: the retry policy is the SDK's, tested there.
        $config->set('poli-page.retries.max_attempts', 0);
        $app->forgetInstance(PoliPage::class);

        return $mock;
    }
}

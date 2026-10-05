<?php

declare(strict_types=1);

namespace PoliPage\Laravel\Tests\Unit\Http;

use GuzzleHttp\Psr7\Utils;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PoliPage\DocumentDescriptor;
use PoliPage\DocumentPreviewResult;
use PoliPage\Laravel\Http\PoliPageResponseFactory;
use PoliPage\PreviewResult;
use PoliPage\RenderMetadata;
use ReflectionClass;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class PoliPageResponseFactoryTest extends TestCase
{
    private PoliPageResponseFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new PoliPageResponseFactory;
    }

    public function test_bytes_returns_response_with_pdf_headers(): void
    {
        $pdf = "%PDF-1.7\n%fake bytes for testing\n%%EOF\n";
        $response = $this->factory->bytes($pdf, 'invoice.pdf');

        self::assertInstanceOf(Response::class, $response);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/pdf', $response->headers->get('Content-Type'));
        self::assertSame((string) strlen($pdf), $response->headers->get('Content-Length'));
        self::assertStringContainsString('attachment', (string) $response->headers->get('Content-Disposition'));
        self::assertMatchesRegularExpression('/filename="?invoice\.pdf"?/', (string) $response->headers->get('Content-Disposition'));
        self::assertCacheControlPrivateNoStore($response->headers->get('Cache-Control'));
        self::assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        self::assertSame($pdf, $response->getContent());
    }

    public function test_bytes_inline_flips_disposition(): void
    {
        $response = $this->factory->bytes('%PDF-1.7', 'report.pdf', inline: true);
        self::assertStringContainsString('inline', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_bytes_non_ascii_filename_uses_rfc5987_encoding(): void
    {
        $response = $this->factory->bytes('%PDF-1.7', 'résumé François.pdf');
        $disposition = (string) $response->headers->get('Content-Disposition');
        self::assertStringContainsString('filename=', $disposition);
        self::assertStringContainsString("filename*=utf-8''", $disposition);
    }

    /**
     * Same cases as poli-page/django#1. Quoting and `"`/`\` escaping are HeaderUtils's job;
     * HeaderUtils forbids `/` and `\` (path separators) and `%` in the fallback, so those
     * are replaced instead of making the response throw.
     *
     * @return iterable<string, array{string, string}>
     */
    public static function contentDispositionCases(): iterable
    {
        yield 'double-quote-is-escaped' => ['say "hi".pdf', 'attachment; filename="say \"hi\".pdf"'];
        yield 'backslash-is-replaced-instead-of-throwing' => ['a\b.pdf', 'attachment; filename=a_b.pdf'];
        yield 'crlf-is-stripped' => ["evil.pdf\r\nSet-Cookie: sid=1", 'attachment; filename="evil.pdfSet-Cookie: sid=1"'];
        yield 'control-chars-are-stripped' => ["tab\there\x00\x1f\x7f.pdf", 'attachment; filename=tabhere.pdf'];
        yield 'parameter-injection-stays-inside-the-quoted-string' => [
            'x.pdf"; filename="pwn.exe',
            'attachment; filename="x.pdf\"; filename=\"pwn.exe"',
        ];
        yield 'non-ascii-uses-rfc5987-dual-notation' => [
            'résumé François.pdf',
            "attachment; filename=\"r?sum? Fran?ois.pdf\"; filename*=utf-8''r%C3%A9sum%C3%A9%20Fran%C3%A7ois.pdf",
        ];
        yield 'non-ascii-fallback-is-escaped' => [
            'résumé "final"\v2.pdf',
            "attachment; filename=\"r?sum? \\\"final\\\"_v2.pdf\"; filename*=utf-8''r%C3%A9sum%C3%A9%20%22final%22_v2.pdf",
        ];
        yield 'non-ascii-control-chars-are-stripped-from-both-forms' => [
            "résumé\r\n\u{85}.pdf",
            "attachment; filename=\"r?sum?.pdf\"; filename*=utf-8''r%C3%A9sum%C3%A9.pdf",
        ];
        yield 'percent-sign-does-not-throw' => ['100%.pdf', "attachment; filename=\"100?.pdf\"; filename*=utf-8''100%25.pdf"];
        yield 'slash-is-replaced-instead-of-throwing' => ['Q1/Q2.pdf', 'attachment; filename=Q1_Q2.pdf'];
        yield 'only-control-chars-falls-back-to-default' => ["\r\n\t", 'attachment; filename=document.pdf'];
    }

    #[DataProvider('contentDispositionCases')]
    public function test_bytes_content_disposition_is_rfc6266_safe(string $filename, string $expected): void
    {
        $response = $this->factory->bytes('%PDF-1.7', $filename);
        self::assertSame($expected, $response->headers->get('Content-Disposition'));
    }

    public function test_stream_content_disposition_is_escaped_and_stripped(): void
    {
        $response = $this->factory->stream(Utils::streamFor('x'), "q\"\r\n.pdf", inline: true);
        self::assertSame('inline; filename="q\".pdf"', $response->headers->get('Content-Disposition'));
    }

    public function test_stream_returns_streamed_response(): void
    {
        $stream = Utils::streamFor('%PDF-1.7 streamed bytes');
        $response = $this->factory->stream($stream, 'streamed.pdf');

        self::assertInstanceOf(StreamedResponse::class, $response);
        self::assertSame('application/pdf', $response->headers->get('Content-Type'));
        self::assertCacheControlPrivateNoStore($response->headers->get('Cache-Control'));
        self::assertMatchesRegularExpression('/filename="?streamed\.pdf"?/', (string) $response->headers->get('Content-Disposition'));

        ob_start();
        $response->sendContent();
        $emitted = (string) ob_get_clean();
        self::assertSame('%PDF-1.7 streamed bytes', $emitted);
    }

    public function test_preview_returns_html_response(): void
    {
        $preview = new PreviewResult('<html>...</html>', 3, 'sandbox');
        $response = $this->factory->preview($preview);

        self::assertInstanceOf(Response::class, $response);
        self::assertSame('text/html; charset=utf-8', $response->headers->get('Content-Type'));
        self::assertCacheControlPrivateNoStore($response->headers->get('Cache-Control'));
        self::assertSame('<html>...</html>', $response->getContent());
    }

    public function test_preview_accepts_document_preview_result(): void
    {
        $preview = new DocumentPreviewResult('<html>stored</html>', 5);
        $response = $this->factory->preview($preview);
        self::assertSame('<html>stored</html>', $response->getContent());
    }

    public function test_document_redirect_goes_to_302_presigned_url(): void
    {
        $descriptor = $this->makeDescriptor('https://cdn.example/abc.pdf?sig=xyz');
        $response = $this->factory->documentRedirect($descriptor);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame(302, $response->getStatusCode());
        self::assertSame('https://cdn.example/abc.pdf?sig=xyz', $response->getTargetUrl());
        self::assertCacheControlPrivateNoStore($response->headers->get('Cache-Control'));
    }

    private static function assertCacheControlPrivateNoStore(?string $header): void
    {
        // Symfony alphabetises Cache-Control directives, so the literal output
        // is 'no-store, private'. Assert presence of both, not exact order.
        self::assertNotNull($header);
        self::assertStringContainsString('private', $header);
        self::assertStringContainsString('no-store', $header);
    }

    private function makeDescriptor(string $url): DocumentDescriptor
    {
        $reflection = new ReflectionClass(DocumentDescriptor::class);
        /** @var DocumentDescriptor $instance */
        $instance = $reflection->newInstanceWithoutConstructor();
        foreach ([
            'documentId' => 'doc_abc',
            'organizationId' => 'org_abc',
            'projectId' => null,
            'projectSlug' => null,
            'templateId' => null,
            'templateSlug' => null,
            'version' => null,
            'environment' => 'sandbox',
            'apiKeyId' => null,
            'format' => 'A4',
            'orientation' => null,
            'locale' => null,
            'pageCount' => 1,
            'sizeBytes' => 1234,
            'createdAt' => '2026-05-26T00:00:00Z',
            'metadata' => new RenderMetadata([]),
            'presignedPdfUrl' => $url,
            'expiresAt' => '2026-05-26T00:15:00Z',
        ] as $name => $value) {
            if ($reflection->hasProperty($name)) {
                $reflection->getProperty($name)->setValue($instance, $value);
            }
        }

        return $instance;
    }
}

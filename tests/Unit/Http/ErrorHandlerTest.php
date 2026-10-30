<?php

declare(strict_types=1);

namespace PoliPage\Laravel\Tests\Unit\Http;

use GuzzleHttp\Psr7\PumpStream;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Foundation\Application;
use Illuminate\Routing\Router;
use LogicException;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use PoliPage\Exception\ConnectionException;
use PoliPage\Exception\NotFoundException;
use PoliPage\Exception\TimeoutException;
use PoliPage\Laravel\Http\PoliPageResponseFactory;
use PoliPage\Laravel\Tests\TestCase;
use PoliPage\PoliPageException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ErrorHandlerTest extends TestCase
{
    private static ?PoliPageException $toThrow = null;

    /**
     * @param  Router  $router
     */
    protected function defineRoutes($router): void
    {
        $router->get('/throws', static function (): never {
            throw self::$toThrow ?? new LogicException('no exception set');
        });
        // A streamed PDF whose download fails on its first read.
        $router->get('/stream-fails', static function (PoliPageResponseFactory $factory): StreamedResponse {
            return $factory->stream(new PumpStream(static function (): never {
                throw self::$toThrow ?? new LogicException('no exception set');
            }), 'report.pdf');
        });
    }

    /**
     * @param  Application  $app
     */
    protected function disableErrorHandler($app): void
    {
        $app->make(Repository::class)->set('poli-page.error_handler', false);
        $app->make(Repository::class)->set('app.debug', false);
    }

    public function test_an_api_error_answers_its_status_and_payload_by_default(): void
    {
        self::$toThrow = new NotFoundException(
            "Template 'missing' not found in project 'billing'.",
            'TEMPLATE_NOT_FOUND',
            404,
            'req_123',
        );

        $response = $this->get('/throws');

        $response->assertStatus(404);
        self::assertSame('application/json', $response->headers->get('Content-Type'));
        self::assertSame([
            'code' => 'TEMPLATE_NOT_FOUND',
            'message' => "Template 'missing' not found in project 'billing'.",
            'status' => 404,
            'requestId' => 'req_123',
        ], $response->json());
    }

    public function test_a_network_error_answers_503(): void
    {
        self::$toThrow = new ConnectionException('Network request failed', PoliPageException::NETWORK_ERROR);

        $response = $this->get('/throws');

        $response->assertStatus(503);
        self::assertSame([
            'code' => 'network_error',
            'message' => 'Network request failed',
            'status' => 503,
            'requestId' => null,
        ], $response->json());
    }

    public function test_a_timeout_answers_504(): void
    {
        self::$toThrow = new TimeoutException('Request timed out after 60000ms', PoliPageException::TIMEOUT);

        $response = $this->get('/throws');

        $response->assertStatus(504);
        self::assertSame([
            'code' => 'timeout',
            'message' => 'Request timed out after 60000ms',
            'status' => 504,
            'requestId' => null,
        ], $response->json());
    }

    public function test_an_sdk_error_without_status_answers_500_with_a_null_status(): void
    {
        self::$toThrow = new PoliPageException('apiKey is required', PoliPageException::INVALID_OPTIONS);

        $response = $this->get('/throws');

        $response->assertStatus(500);
        self::assertSame('application/json', $response->headers->get('Content-Type'));
        self::assertSame([
            'code' => 'invalid_options',
            'message' => 'apiKey is required',
            'status' => null,
            'requestId' => null,
        ], $response->json());
    }

    public function test_a_stream_whose_first_read_fails_answers_json_without_pdf_headers(): void
    {
        self::$toThrow = new PoliPageException('Failed to download PDF: network error', PoliPageException::DOWNLOAD_FAILED);

        $response = $this->get('/stream-fails');

        $response->assertStatus(500);
        self::assertSame('application/json', $response->headers->get('Content-Type'));
        self::assertNull($response->headers->get('Content-Disposition'));
        self::assertSame([
            'code' => 'DOWNLOAD_FAILED',
            'message' => 'Failed to download PDF: network error',
            'status' => null,
            'requestId' => null,
        ], $response->json());
    }

    #[DefineEnvironment('disableErrorHandler')]
    public function test_the_handler_is_not_registered_when_disabled(): void
    {
        self::$toThrow = new NotFoundException('Not found', 'TEMPLATE_NOT_FOUND', 404, 'req_123');

        $response = $this->get('/throws');

        // Laravel's own handling: a plain 500, not the SDK's status and payload.
        $response->assertStatus(500);
        self::assertStringNotContainsString('TEMPLATE_NOT_FOUND', (string) $response->getContent());
    }

    protected function tearDown(): void
    {
        self::$toThrow = null;
        parent::tearDown();
    }
}

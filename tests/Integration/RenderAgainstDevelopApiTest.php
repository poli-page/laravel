<?php

declare(strict_types=1);

namespace PoliPage\Laravel\Tests\Integration;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Foundation\Application;
use PoliPage\Laravel\Tests\TestCase;
use PoliPage\PoliPage;
use PoliPage\RenderInput;

final class RenderAgainstDevelopApiTest extends TestCase
{
    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        // Why: the package refuses an empty api_key. Without a real key the
        // test body skips before the client is resolved, so this well-formed
        // placeholder never reaches the API.
        $key = (string) getenv('POLI_PAGE_API_KEY');
        $config = $app->make(Repository::class);
        $config->set('poli-page.api_key', $key !== '' ? $key : self::SANDBOX_API_KEY);
        $testBaseUrl = getenv('POLI_PAGE_TEST_BASE_URL');
        if (is_string($testBaseUrl) && $testBaseUrl !== '') {
            $config->set('poli-page.base_url', $testBaseUrl);
        }
        $config->set('poli-page.timeout', 30.0);
    }

    public function test_render_getting_started_welcome_template_returns_pdf(): void
    {
        // Why: skip check lives in the test method (not setUp) so that
        // RestoresGlobalHandlers captures its baseline cleanly before any
        // markTestSkipped() short-circuits the test lifecycle. Otherwise
        // PHPUnit 11.5+ flags the test as risky on a clean CI without secrets.
        $key = getenv('POLI_PAGE_API_KEY');
        if ($key === false || $key === '') {
            self::markTestSkipped('POLI_PAGE_API_KEY not set; skipping develop-API integration test.');
        }
        if (! str_starts_with($key, 'pp_sandbox_')) {
            self::markTestSkipped('POLI_PAGE_API_KEY must be a pp_sandbox_ key; refusing to run the integration test in a counted environment.');
        }

        $app = $this->app;
        \assert($app !== null);

        $client = $app->make(PoliPage::class);

        $pdf = $client->render->pdf(new RenderInput(
            project: 'getting-started',
            template: 'welcome',
            data: ['name' => 'laravel-package integration test'],
        ));

        self::assertNotEmpty($pdf);
        self::assertStringStartsWith('%PDF-', $pdf);
    }
}

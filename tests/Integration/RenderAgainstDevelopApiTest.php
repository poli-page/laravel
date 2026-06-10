<?php

declare(strict_types=1);

namespace PoliPage\Laravel\Tests\Integration;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Foundation\Application;
use PoliPage\Laravel\Tests\TestCase;
use PoliPage\PoliPage;
use PoliPage\ProjectModeInput;

final class RenderAgainstDevelopApiTest extends TestCase
{
    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        // Why: must satisfy the package's api_key validator (pp_test_ prefix) so
        // parent::setUp() can register the singleton without throwing during
        // Testbench boot. The test body itself checks for a real key and skips
        // when absent. Using a dummy pp_test_ value here is harmless — the
        // singleton is only instantiated inside the test method, after the
        // skip check.
        $key = (string) getenv('POLI_PAGE_API_KEY');
        $config = $app->make(Repository::class);
        $config->set('poli-page.api_key', $key !== '' ? $key : 'pp_test_placeholder_for_setup_only');
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
        if (! str_starts_with($key, 'pp_test_')) {
            self::markTestSkipped('POLI_PAGE_API_KEY must be a pp_test_ key; refusing to run integration test against a live key.');
        }

        $app = $this->app;
        \assert($app !== null);

        $client = $app->make(PoliPage::class);

        $pdf = $client->render->pdf(new ProjectModeInput(
            project: 'getting-started',
            template: 'welcome',
            data: ['name' => 'laravel-package integration test'],
            version: '1.0.0',
        ));

        self::assertNotEmpty($pdf);
        self::assertStringStartsWith('%PDF-', $pdf);
    }
}

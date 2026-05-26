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
    protected function setUp(): void
    {
        $key = getenv('POLI_PAGE_API_KEY');
        if ($key === false || $key === '') {
            self::markTestSkipped('POLI_PAGE_API_KEY not set; skipping develop-API integration test.');
        }
        if (! str_starts_with($key, 'pp_test_')) {
            self::markTestSkipped('POLI_PAGE_API_KEY must be a pp_test_ key; refusing to run integration test against a live key.');
        }
        parent::setUp();
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $key = (string) getenv('POLI_PAGE_API_KEY');
        $config = $app->make(Repository::class);
        $config->set('poli-page.api_key', $key);
        $config->set('poli-page.base_url', 'https://api-develop.poli.page');
        $config->set('poli-page.timeout', 30.0);
    }

    public function test_render_getting_started_welcome_template_returns_pdf(): void
    {
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

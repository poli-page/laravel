<?php

declare(strict_types=1);

namespace PoliPage\Laravel\Tests\Unit\Console;

use Illuminate\Support\Facades\Artisan;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\LegacyMockInterface;
use Mockery\MockInterface;
use PoliPage\InlineModeInput;
use PoliPage\Laravel\Tests\TestCase;
use PoliPage\PoliPage;
use PoliPage\PoliPageException;
use PoliPage\PreviewResult;
use PoliPage\ProjectModeInput;
use PoliPage\Render;
use ReflectionClass;

final class RenderCommandTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private string $outputPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->outputPath = sys_get_temp_dir().'/poli-page-render-test-'.uniqid().'.pdf';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->outputPath)) {
            unlink($this->outputPath);
        }
        $htmlPath = preg_replace('/\.pdf$/', '.html', $this->outputPath);
        if ($htmlPath !== null && file_exists($htmlPath)) {
            unlink($htmlPath);
        }
        parent::tearDown();
    }

    public function test_project_mode_writes_pdf_bytes(): void
    {
        $app = $this->app;
        \assert($app !== null);

        $render = $this->mockRender();
        $render->shouldReceive('pdf')
            ->once()
            ->andReturnUsing(function (ProjectModeInput $input): string {
                self::assertSame('invoices', $input->project);
                self::assertSame('default', $input->template);
                self::assertSame('1.0.0', $input->version);
                self::assertSame(['name' => 'Ada'], $input->data);

                return "%PDF-1.7\nstub\n%%EOF\n";
            });

        $app->instance(PoliPage::class, $this->stubClient($render));

        $exitCode = Artisan::call('poli-page:render', [
            '--project' => 'invoices',
            '--template' => 'default',
            '--template-version' => '1.0.0',
            '--data' => '{"name":"Ada"}',
            '--output' => $this->outputPath,
        ]);

        self::assertSame(0, $exitCode);
        self::assertFileExists($this->outputPath);
        self::assertStringStartsWith('%PDF-', (string) file_get_contents($this->outputPath));
        self::assertStringContainsString('Rendered', Artisan::output());
    }

    public function test_inline_html_mode_for_preview(): void
    {
        $app = $this->app;
        \assert($app !== null);

        $render = $this->mockRender();
        $render->shouldReceive('preview')
            ->once()
            ->andReturnUsing(function (InlineModeInput $input): PreviewResult {
                self::assertSame('<h1>Hi</h1>', $input->template);

                return new PreviewResult('<html><h1>Hi</h1></html>', 1, 'sandbox');
            });

        $app->instance(PoliPage::class, $this->stubClient($render));

        $htmlInput = sys_get_temp_dir().'/poli-page-render-input-'.uniqid().'.html';
        file_put_contents($htmlInput, '<h1>Hi</h1>');
        $htmlOutput = preg_replace('/\.pdf$/', '.html', $this->outputPath);
        \assert($htmlOutput !== null);

        $exitCode = Artisan::call('poli-page:render', [
            '--html' => $htmlInput,
            '--template' => 'inline',
            '--preview' => true,
            '--output' => $htmlOutput,
        ]);
        unlink($htmlInput);

        self::assertSame(0, $exitCode);
        self::assertFileExists($htmlOutput);
        self::assertStringContainsString('<h1>Hi</h1>', (string) file_get_contents($htmlOutput));
    }

    public function test_poli_page_exception_exits_with_mapped_code(): void
    {
        $app = $this->app;
        \assert($app !== null);

        $render = $this->mockRender();
        $render->shouldReceive('pdf')->andThrow(
            new PoliPageException('bad version', 'INVALID_VERSION_FORMAT', 400),
        );

        $app->instance(PoliPage::class, $this->stubClient($render));

        $exitCode = Artisan::call('poli-page:render', [
            '--project' => 'p',
            '--template' => 't',
            '--template-version' => 'bad',
            '--data' => '{}',
            '--output' => $this->outputPath,
        ]);

        self::assertSame(1, $exitCode, '4xx PoliPageException should exit with code 1');
        self::assertStringContainsString('INVALID_VERSION_FORMAT', Artisan::output());
    }

    /**
     * Mockery mocks of final classes work at runtime via bypass-finals
     * (tests/bootstrap.php). The return type uses Mockery's own interface
     * so PHPStan does not need to follow into a Render-derived class shape.
     */
    private function mockRender(): MockInterface&LegacyMockInterface
    {
        /** @var MockInterface&LegacyMockInterface $mock */
        $mock = Mockery::mock(Render::class);

        return $mock;
    }

    private function stubClient(MockInterface $render): PoliPage
    {
        $reflection = new ReflectionClass(PoliPage::class);
        /** @var PoliPage $client */
        $client = $reflection->newInstanceWithoutConstructor();
        $reflection->getProperty('render')->setValue($client, $render);

        return $client;
    }
}

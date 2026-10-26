<?php

declare(strict_types=1);

namespace PoliPage\Laravel\Tests\Unit\Console;

use Illuminate\Support\Facades\Artisan;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\LegacyMockInterface;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PoliPage\Laravel\Tests\TestCase;
use PoliPage\PoliPage;
use PoliPage\PoliPageException;
use PoliPage\Render;
use PoliPage\RenderInput;
use ReflectionClass;
use Symfony\Component\Console\Exception\InvalidOptionException;

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
        parent::tearDown();
    }

    public function test_render_writes_pdf_bytes(): void
    {
        $app = $this->app;
        \assert($app !== null);

        $render = $this->mockRender();
        $render->shouldReceive('pdf')
            ->once()
            ->andReturnUsing(function (RenderInput $input): string {
                self::assertSame('getting-started', $input->project);
                self::assertSame('welcome', $input->template);
                self::assertSame(['name' => 'Ada'], $input->data);

                return "%PDF-1.7\nstub\n%%EOF\n";
            });

        $app->instance(PoliPage::class, $this->stubClient($render));

        $exitCode = Artisan::call('poli-page:render', [
            '--project' => 'getting-started',
            '--template' => 'welcome',
            '--data' => '{"name":"Ada"}',
            '--output' => $this->outputPath,
        ]);

        self::assertSame(0, $exitCode);
        self::assertFileExists($this->outputPath);
        self::assertStringStartsWith('%PDF-', (string) file_get_contents($this->outputPath));
        self::assertStringContainsString('Rendered', Artisan::output());
    }

    public function test_missing_project_or_template_is_invalid(): void
    {
        $app = $this->app;
        \assert($app !== null);

        $render = $this->mockRender();
        $render->shouldNotReceive('pdf');
        $app->instance(PoliPage::class, $this->stubClient($render));

        $exitCode = Artisan::call('poli-page:render', [
            '--project' => 'getting-started',
            '--output' => $this->outputPath,
        ]);

        self::assertSame(2, $exitCode);
        self::assertStringContainsString('--project and --template are required', Artisan::output());
    }

    /** @return iterable<string, array{0: string, 1: string|bool}> */
    public static function removedOptionProvider(): iterable
    {
        yield 'template version' => ['--template-version', '1.0.0'];
        yield 'preview' => ['--preview', true];
        yield 'inline html' => ['--html', 'template.html'];
    }

    #[DataProvider('removedOptionProvider')]
    public function test_removed_options_no_longer_exist(string $option, string|bool $value): void
    {
        $this->expectException(InvalidOptionException::class);

        Artisan::call('poli-page:render', [
            '--project' => 'getting-started',
            '--template' => 'welcome',
            $option => $value,
            '--output' => $this->outputPath,
        ]);
    }

    public function test_poli_page_exception_exits_with_mapped_code(): void
    {
        $app = $this->app;
        \assert($app !== null);

        $render = $this->mockRender();
        $render->shouldReceive('pdf')->andThrow(
            new PoliPageException('Template not found', 'TEMPLATE_NOT_FOUND', 404),
        );

        $app->instance(PoliPage::class, $this->stubClient($render));

        $exitCode = Artisan::call('poli-page:render', [
            '--project' => 'getting-started',
            '--template' => 'does-not-exist',
            '--data' => '{}',
            '--output' => $this->outputPath,
        ]);

        self::assertSame(1, $exitCode, '4xx PoliPageException should exit with code 1');
        self::assertStringContainsString('TEMPLATE_NOT_FOUND', Artisan::output());
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

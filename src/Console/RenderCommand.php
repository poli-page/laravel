<?php

declare(strict_types=1);

namespace PoliPage\Laravel\Console;

use Illuminate\Console\Command;
use InvalidArgumentException;
use PoliPage\InlineModeInput;
use PoliPage\PoliPage;
use PoliPage\PoliPageException;
use PoliPage\ProjectModeInput;
use RuntimeException;
use Throwable;

/**
 * `php artisan poli-page:render` — smoke-test the integration end to end.
 *
 * Why `--template-version` and not `--version`: Artisan (Symfony Console)
 * reserves `--version`/`-V` globally. Redefining it silently shadows the
 * global flag and the command produces no output. See CLAUDE.md §10.1.
 */
final class RenderCommand extends Command
{
    /** @var string */
    protected $signature = 'poli-page:render
        {--project= : Project slug (required unless --html is given)}
        {--template= : Template slug}
        {--template-version= : Template version (required unless --html is given)}
        {--data= : Inline JSON for the data payload}
        {--data-file= : Read data payload from a file (- for stdin)}
        {--html= : Inline-mode: render raw HTML from a file (preview only)}
        {--o|output=./poli-page-render.pdf : Output file path}
        {--preview : Render HTML preview instead of PDF}';

    /** @var string */
    protected $description = 'Smoke-test the Poli Page package by rendering a template end-to-end.';

    public function __construct(private readonly PoliPage $client)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $data = $this->resolveData();

            return $this->option('preview')
                ? $this->doPreview($data)
                : $this->doPdf($data);
        } catch (PoliPageException $e) {
            $this->error(sprintf(
                '%s (status=%s, code=%s, requestId=%s)',
                $e->getMessage(),
                $e->status ?? 'n/a',
                $e->errorCode,
                $e->requestId ?? 'n/a',
            ));

            return $this->exitCodeFor($e);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function doPdf(array $data): int
    {
        $project = $this->stringOption('project');
        $template = $this->stringOption('template');
        $version = $this->stringOption('template-version');

        if ($project === '' || $template === '' || $version === '') {
            $this->error('--project, --template and --template-version are required for PDF rendering.');

            return self::INVALID;
        }

        $start = microtime(true);
        $pdf = $this->client->render->pdf(new ProjectModeInput(
            project: $project,
            template: $template,
            data: $data,
            version: $version,
        ));
        $elapsedMs = (int) round((microtime(true) - $start) * 1000);

        $outputPath = $this->stringOption('output');
        $this->writeFile($outputPath, $pdf);

        $this->info(sprintf(
            'Rendered %d bytes in %dms. Wrote to %s.',
            strlen($pdf),
            $elapsedMs,
            $outputPath,
        ));

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function doPreview(array $data): int
    {
        $htmlPath = $this->stringOption('html');
        $template = $this->stringOption('template');

        if ($htmlPath !== '') {
            $html = $this->readFile($htmlPath);
            $previewInput = new InlineModeInput(template: $html, data: $data);
        } else {
            $project = $this->stringOption('project');
            $version = $this->stringOption('template-version');
            if ($project === '' || $template === '' || $version === '') {
                $this->error('Either --html, or all of --project --template --template-version, are required for preview.');

                return self::INVALID;
            }
            $previewInput = new ProjectModeInput(
                project: $project,
                template: $template,
                data: $data,
                version: $version,
            );
        }

        $start = microtime(true);
        $result = $this->client->render->preview($previewInput);
        $elapsedMs = (int) round((microtime(true) - $start) * 1000);

        $outputPath = $this->stringOption('output');
        if (str_ends_with($outputPath, '.pdf')) {
            $outputPath = substr($outputPath, 0, -4).'.html';
        }
        $this->writeFile($outputPath, $result->html);

        $this->info(sprintf(
            'Rendered %d pages of HTML preview in %dms. Wrote to %s.',
            $result->totalPages,
            $elapsedMs,
            $outputPath,
        ));

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveData(): array
    {
        $dataFile = $this->stringOption('data-file');
        if ($dataFile !== '') {
            $contents = $dataFile === '-'
                ? (string) stream_get_contents(STDIN)
                : $this->readFile($dataFile);
        } else {
            $contents = $this->stringOption('data');
        }
        if ($contents === '') {
            return [];
        }
        /** @var mixed $decoded */
        $decoded = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($decoded)) {
            throw new InvalidArgumentException('--data / --data-file must decode to a JSON object.');
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    /**
     * String-or-empty getter for Artisan options. The Console contract
     * returns array|bool|string|null, but every option in this command is
     * declared as a scalar string — coerce safely and centralise here.
     */
    private function stringOption(string $name): string
    {
        $value = $this->option($name);
        if ($value === null || $value === false || $value === '') {
            return '';
        }
        if (is_array($value)) {
            return '';
        }

        return (string) $value;
    }

    private function readFile(string $path): string
    {
        $contents = @file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException(sprintf('Could not read file: %s', $path));
        }

        return $contents;
    }

    private function writeFile(string $path, string $contents): void
    {
        $dir = dirname($path);
        if (! is_dir($dir) && ! @mkdir($dir, 0o755, true) && ! is_dir($dir)) {
            throw new RuntimeException(sprintf('Could not create output directory: %s', $dir));
        }
        if (@file_put_contents($path, $contents) === false) {
            throw new RuntimeException(sprintf('Could not write to: %s', $path));
        }
    }

    private function exitCodeFor(PoliPageException $e): int
    {
        $status = $e->status ?? 0;
        if ($status >= 400 && $status < 500) {
            return 1;
        }
        if ($status >= 500) {
            return 2;
        }

        return 3;
    }
}

<?php

declare(strict_types=1);

namespace PoliPage\Laravel\Console;

use Illuminate\Console\Command;
use InvalidArgumentException;
use PoliPage\PoliPage;
use PoliPage\PoliPageException;
use PoliPage\RenderInput;
use RuntimeException;
use Throwable;

/**
 * `php artisan poli-page:render` — smoke-test the integration end to end.
 */
final class RenderCommand extends Command
{
    /** @var string */
    protected $signature = 'poli-page:render
        {--project= : Project slug}
        {--template= : Template slug}
        {--data= : Inline JSON for the data payload}
        {--data-file= : Read data payload from a file (- for stdin)}
        {--o|output=./poli-page-render.pdf : Output file path}';

    /** @var string */
    protected $description = 'Smoke-test the Poli Page package by rendering a template end-to-end.';

    public function __construct(private readonly PoliPage $client)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            return $this->doPdf($this->resolveData());
        } catch (PoliPageException $e) {
            $this->error(sprintf(
                '%s (status=%s, code=%s, requestId=%s)',
                $this->withoutApiKey($e->getMessage()),
                $e->status ?? 'n/a',
                $e->errorCode,
                $e->requestId ?? 'n/a',
            ));

            return $this->exitCodeFor($e);
        } catch (Throwable $e) {
            $this->error($this->withoutApiKey($e->getMessage()));

            return self::FAILURE;
        }
    }

    /**
     * Why: a key the HTTP layer rejects (e.g. one ending in a newline) makes
     * PSR-7 throw with the whole "Bearer <key>" header value in its message.
     * Guzzle trims " \t" from the value first, so the trimmed key is redacted too.
     */
    private function withoutApiKey(string $message): string
    {
        $apiKey = config('poli-page.api_key');
        if (! is_string($apiKey)) {
            return $message;
        }

        // str_replace skips an empty search (a key made only of spaces or tabs).
        return str_replace([$apiKey, rtrim($apiKey, " \t")], '[redacted API key]', $message);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function doPdf(array $data): int
    {
        $project = $this->stringOption('project');
        $template = $this->stringOption('template');

        if ($project === '' || $template === '') {
            $this->error('--project and --template are required.');

            return self::INVALID;
        }

        $start = microtime(true);
        $pdf = $this->client->render->pdf(new RenderInput(
            project: $project,
            template: $template,
            data: $data,
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

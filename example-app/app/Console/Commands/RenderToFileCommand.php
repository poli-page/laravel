<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PoliPage\PoliPage;
use PoliPage\ProjectModeInput;

use function PoliPage\renderToFile;

/**
 * Demo step 3: the renderToFile() free function from src/render_to_file.php.
 *
 * In an `app:` namespace to avoid colliding with the package's own
 * poli-page:render command.
 */
final class RenderToFileCommand extends Command
{
    /** @var string */
    protected $signature = 'app:demo:render-to-file';

    /** @var string */
    protected $description = 'Demo of the free renderToFile() helper from the SDK.';

    public function __construct(private readonly PoliPage $client)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $path = sys_get_temp_dir() . '/poli-page-demo-file.pdf';

        renderToFile($this->client, new ProjectModeInput(
            project: 'getting-started',
            template: 'welcome',
            data: ['name' => 'renderToFile demo'],
            version: '1.0.0',
        ), $path);

        $this->info(sprintf('Wrote PDF to %s (%d bytes).', $path, filesize($path) ?: 0));

        return self::SUCCESS;
    }
}

<?php

declare(strict_types=1);

namespace PoliPage\Laravel\Tests;

use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;
use Stringable;

final class RecordingLogger extends AbstractLogger
{
    /** @var list<string> */
    public array $warnings = [];

    /**
     * @param  array<mixed>  $context
     */
    public function log($level, string|Stringable $message, array $context = []): void
    {
        if ($level === LogLevel::WARNING) {
            $this->warnings[] = (string) $message;
        }
    }
}

<?php

declare(strict_types=1);

namespace PoliPage\Laravel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PoliPage\PoliPage;

final class SmokeTest extends TestCase
{
    public function test_pipeline_runs(): void
    {
        self::assertTrue(class_exists(PoliPage::class), 'SDK autoloader must be wired.');
    }
}

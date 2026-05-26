<?php

declare(strict_types=1);

namespace PoliPage\Laravel\Tests;

use Throwable;

/**
 * Snapshots the global error/exception handler stack in setUp() and unwinds
 * back to that baseline in tearDown().
 *
 * Why: Testbench's HandleExceptions::bootstrap() registers global error
 * and exception handlers via set_error_handler / set_exception_handler.
 * The kernel shutdown sequence does NOT unwind them in every scenario.
 * PHPUnit 11.5+ marks any test that ends with a different handler stack
 * as risky. This trait restores the baseline so tests that boot a
 * Testbench app are no longer flagged.
 *
 * Usage: `use RestoresGlobalHandlers;` in any TestCase that boots Testbench.
 */
trait RestoresGlobalHandlers
{
    private mixed $errorHandlerBaseline = null;
    private mixed $exceptionHandlerBaseline = null;

    protected function setUp(): void
    {
        $this->errorHandlerBaseline = self::peekErrorHandler();
        $this->exceptionHandlerBaseline = self::peekExceptionHandler();
        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        self::popUntil(
            self::peekErrorHandler(...),
            restore_error_handler(...),
            $this->errorHandlerBaseline,
        );
        self::popUntil(
            self::peekExceptionHandler(...),
            restore_exception_handler(...),
            $this->exceptionHandlerBaseline,
        );
    }

    private static function peekErrorHandler(): mixed
    {
        $current = set_error_handler(static fn (int $errno, string $errstr): bool => false);
        restore_error_handler();

        return $current;
    }

    private static function peekExceptionHandler(): mixed
    {
        $current = set_exception_handler(static function (Throwable $e): void {});
        restore_exception_handler();

        return $current;
    }

    private static function popUntil(callable $peek, callable $pop, mixed $target): void
    {
        for ($i = 0; $i < 50; ++$i) {
            $current = $peek();
            if ($current === $target || null === $current) {
                return;
            }
            $pop();
        }
    }
}

<?php

declare(strict_types=1);

namespace PoliPage\Laravel\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use PoliPage\Laravel\Facades\PoliPage;
use PoliPage\Laravel\PoliPageServiceProvider;

abstract class TestCase extends BaseTestCase
{
    use RestoresGlobalHandlers;

    /**
     * @param  \Illuminate\Foundation\Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [PoliPageServiceProvider::class];
    }

    /**
     * @param  \Illuminate\Foundation\Application  $app
     * @return array<string, class-string>
     */
    protected function getPackageAliases($app): array
    {
        return ['PoliPage' => PoliPage::class];
    }

    /**
     * @param  \Illuminate\Foundation\Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('poli-page.api_key', 'pp_test_unit_default');
    }
}

<?php

declare(strict_types=1);

namespace PoliPage\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use PoliPage\Documents;
use PoliPage\PoliPage as PoliPageClient;
use PoliPage\Render;

/**
 * Laravel Facade for the Poli Page SDK client.
 *
 * The SDK exposes `render` and `documents` as public readonly *properties*,
 * not methods. Laravel Facades proxy method calls only, so this facade
 * provides `render()` / `documents()` methods that return those properties.
 *
 * @method static Render render()
 * @method static Documents documents()
 *
 * @see PoliPageClient
 */
final class PoliPage extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return PoliPageClient::class;
    }

    public static function render(): Render
    {
        /** @var PoliPageClient $client */
        $client = self::getFacadeRoot();

        return $client->render;
    }

    public static function documents(): Documents
    {
        /** @var PoliPageClient $client */
        $client = self::getFacadeRoot();

        return $client->documents;
    }
}

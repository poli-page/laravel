<?php

declare(strict_types=1);

namespace PoliPage\Laravel\Events;

use PoliPage\PoliPageException;

final readonly class PoliPageErrored
{
    public function __construct(public PoliPageException $exception) {}
}

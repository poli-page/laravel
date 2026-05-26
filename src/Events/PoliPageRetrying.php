<?php

declare(strict_types=1);

namespace PoliPage\Laravel\Events;

use PoliPage\Events\RetryEvent;

final readonly class PoliPageRetrying
{
    public function __construct(public RetryEvent $sdkEvent) {}
}

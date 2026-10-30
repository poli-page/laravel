<?php

declare(strict_types=1);
use DG\BypassFinals;

require __DIR__.'/../vendor/autoload.php';

// Why: the SDK marks Render/Documents/PoliPage as final (correct production
// posture). Bypassing the final keyword at autoload time lets PHPUnit mock
// them in unit tests without forcing the SDK to add interfaces just for us.
BypassFinals::enable();

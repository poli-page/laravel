<?php

declare(strict_types=1);
use DG\BypassFinals;

require __DIR__.'/../vendor/autoload.php';

// Why: the SDK marks Render/Documents/PoliPage as final (correct production
// posture). Bypassing the final keyword at autoload time lets PHPUnit mock
// them in unit tests without forcing the SDK to add interfaces just for us.
BypassFinals::enable();

// Why: single root .env across the entire integrations workspace (no per-app
// .env.local). Real shell exports always win — only set env vars not already
// present in the environment.
//
// Workspace root: /Users/mickael/Projects/symfony-bundle/.env is the de-facto
// shared root (the symfony-bundle was scaffolded first and owns the file).
$root = __DIR__.'/../../symfony-bundle/.env';
if (is_readable($root)) {
    foreach (file($root, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(ltrim($line), '#') || ! str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        $value = trim($value, "\"'");
        if (getenv($key) === false && ! isset($_ENV[$key])) {
            putenv("$key=$value");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

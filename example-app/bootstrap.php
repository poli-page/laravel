<?php

declare(strict_types=1);

// Why: PHP 8.5 deprecates PDO::MYSQL_ATTR_SSL_CA in favour of
// Pdo\Mysql::ATTR_SSL_CA. Laravel 11's bundled vendor config/database.php
// still references the old constant, firing 4 deprecations on every demo
// request. The demo doesn't touch MySQL at all (session driver is array,
// see config/database.php override) — mask deprecations process-locally
// so the demo output stays readable. Real apps on Laravel 12+ won't need
// this. Only affects this example-app process; the package's own tests
// keep full strictness.
error_reporting(error_reporting() & ~E_DEPRECATED & ~E_USER_DEPRECATED);

// Why: shared root .env across the integrations workspace (no .env.local
// step). The symfony-bundle was scaffolded first and owns the file. Real
// shell exports always win.
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

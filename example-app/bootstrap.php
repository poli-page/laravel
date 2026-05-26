<?php

declare(strict_types=1);

// Why: shared root .env across the integrations workspace (no .env.local
// step). The symfony-bundle was scaffolded first and owns the file. Real
// shell exports always win.
$root = __DIR__ . '/../../symfony-bundle/.env';
if (is_readable($root)) {
    foreach (file($root, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(ltrim($line), '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        $value = trim($value, "\"'");
        if (getenv($key) === false && !isset($_ENV[$key])) {
            putenv("$key=$value");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

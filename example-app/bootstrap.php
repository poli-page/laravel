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

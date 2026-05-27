<?php

declare(strict_types=1);

// Why: overrides Laravel 11's bundled vendor/laravel/framework/config/database.php
// which references PDO::MYSQL_ATTR_SSL_CA — deprecated on PHP 8.5+. The demo
// never opens a database connection (session driver is 'array', no migrations),
// so this minimal config exists only to keep the framework's ConfigLoader happy
// without triggering the deprecation warnings.
return [
    'default' => 'sqlite',

    'connections' => [
        'sqlite' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ],
    ],

    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],

    'redis' => [
        'client' => 'phpredis',
        'options' => [],
        'default' => [
            'host' => '127.0.0.1',
            'password' => null,
            'port' => 6379,
            'database' => 0,
        ],
    ],
];

<?php

declare(strict_types=1);
use Illuminate\Http\Request;

require __DIR__.'/../bootstrap.php';
require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$app->handleRequest(Request::capture());

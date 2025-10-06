<?php

require __DIR__ . '/../vendor/autoload.php';

use App\Example;

header('Content-Type: text/plain; charset=utf-8');

echo Example::message() . "\n";

echo "PHP version: " . PHP_VERSION . "\n";

echo "APP_ENV=" . (getenv('APP_ENV') ?: 'undefined') . "\n";

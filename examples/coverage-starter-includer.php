<?php

$appPath = '/path/to/app';
require_once $appPath . '/vendor/autoload.php';

use TurgutSaricam\PHPUnitTools\Coverage\CoverageStarterIncluder;
$includer = new CoverageStarterIncluder(
    '/var/www/html/',
    ['vendor', 'css', 'js', 'public', 'node_modules'],
    'start-coverage.php'
);
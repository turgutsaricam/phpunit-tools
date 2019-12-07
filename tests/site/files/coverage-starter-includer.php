<?php

$appPath = '/usr/src/phpunit-tools';
require_once $appPath . '/vendor/autoload.php';

use TurgutSaricam\PHPUnitTools\Coverage\CoverageStarterIncluder;
$includer = new CoverageStarterIncluder(
    '/var/www/html/',
    ['files'],
    'start-coverage.php'
);

$includer
    ->setCacheFilePath('/var/www/html/files/include-files.txt')
    ->includeFiles();
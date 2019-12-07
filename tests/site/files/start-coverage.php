<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(60);

$toolsPath = '/usr/src/phpunit-tools';
$appPath = '/var/www/html';
require_once $toolsPath . '/vendor/autoload.php';

use TurgutSaricam\PHPUnitTools\Coverage\CoverageHandler;
return new CoverageHandler(
    CoverageHandler::COVERAGE_TYPE_XDEBUG,
    'coverageStartHintKey',
    [
        $appPath,
    ],
    [
        $appPath . '/files',
    ],
    __DIR__ . "/../coverages"
);
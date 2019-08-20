<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(60);

$appPath = '/path/to/app';
require_once $appPath . '/vendor/autoload.php';

use TurgutSaricam\PHPUnitTools\Coverage\CoverageHandler;
return new CoverageHandler(
    CoverageHandler::COVERAGE_TYPE_XDEBUG,
    'coverageStartHintKey',
    [
        $appPath,
    ],
    [
        $appPath . '/vendor',
        $appPath . '/storage',
        $appPath . '/views',
    ],
    __DIR__ . "/coverages"
);
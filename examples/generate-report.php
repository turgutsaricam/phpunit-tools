<?php

$appPath = '/path/to/app/';
require_once("$appPath/vendor/autoload.php");

$generator = new ReportGenerator(
    true,
    true,
    [
        $appPath,
    ],
    [
        $appPath . '/vendor',
        $appPath . '/storage',
        $appPath . '/views',
    ],
    'ui-test-report',
    __DIR__ . "/reports",
    __DIR__ . "/coverages",
    'Europe/Istanbul'
);

$generator->generate();
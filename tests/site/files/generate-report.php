<?php


$appPath = '/var/www/html';
$toolsPath = '/usr/src/phpunit-tools';
require_once $toolsPath . '/vendor/autoload.php';

use TurgutSaricam\PHPUnitTools\Coverage\ReportGenerator;
$generator = new ReportGenerator(
    [
        $appPath,
    ],
    [
        $appPath . '/files',
    ],
    'my-test-report',
    __DIR__ . "/reports",
    __DIR__ . "/../coverages",
    'Europe/Istanbul'
);

$generator
    ->setGenerateClover(true)
    ->setGenerateHtml(true)
    ->setGeneratePHP(true);

$generator->generate();
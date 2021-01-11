<?php

use DummySite\DummyClass;

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'DummyClass.php';

$dummy = new DummyClass();
$dummy
    ->setDummyValue(4)
    ->setDummyValue(3)
    ->setDummyValue(2)
    ->setDummyValue(1)
    ->setDummyValue(0)
    ->showDummyValue();

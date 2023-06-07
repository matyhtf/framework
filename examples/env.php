<?php
require __DIR__ . '/../vendor/autoload.php';
$app = SPF\App::getInstance(dirname(__DIR__));
var_dump(env('TEST_ENV'));

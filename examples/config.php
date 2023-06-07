<?php

require __DIR__ . '/../vendor/autoload.php';
$app = SPF\App::getInstance(dirname(__DIR__));
$app->config->setPath(__DIR__.'/config');

var_dump(config('test'));
var_dump(config('test.a'));
var_dump(config('test.a.b'));
var_dump(config('test.a.b.c'));
var_dump(config('test.a.notfound'));

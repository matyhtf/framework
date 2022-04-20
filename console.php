#!/usr/bin/env php
<?php
require_once __DIR__ . '/vendor/autoload.php';

SPF\App::getInstance(getenv('PWD'))->runConsole();

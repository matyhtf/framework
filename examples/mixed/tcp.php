<?php
define('DEBUG', 'on');
define('WEBPATH', realpath(__DIR__ . '/..'));
//包含框架入口文件
require WEBPATH . '/libs/lib_config.php';
$s = microtime(true);
$c = new SPF\Client\TCP;
$c->connect('127.0.0.1', 9501, 0.5);
$c->send('hello');
var_dump($c->recv());
echo "use ".(microtime(true) - $s)."\n";
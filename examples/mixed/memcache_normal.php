<?php
define('DEBUG', 'on');
define('WEBPATH', realpath(__DIR__ . '/..'));
//包含框架入口文件
require WEBPATH . '/libs/lib_config.php';

$mc = new Memcached();
$mc->addServer('127.0.0.1', 11211);

$res = $mc->set('key2', 1);
echo "set res:".var_export($res,1)."\n";

$res = $mc->get('key2');
echo "get res:".var_export($res,1)."\n";
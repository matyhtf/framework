<?php
define('DEBUG', 'on');
define('WEBPATH', realpath(__DIR__ . '/..'));
//包含框架入口文件
require WEBPATH . '/libs/lib_config.php';

$kv = new \SPF\Memory\Storage();
//var_dump($kv->get('swoole'));
//var_dump($kv->set('swoole', array(123, 456)));
//var_dump($kv->get('swoole'));
//var_dump($kv->del('swoole'));
//var_dump($kv->get('swoole'));

foreach(range(0, 100) as $i)
{
    $kv->set('test:'.$i, $i);
}
var_dump($kv->scan('test:'));
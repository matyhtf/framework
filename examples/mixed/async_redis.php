<?php
define('DEBUG', 'on');
define('WEBPATH', realpath(__DIR__ . '/..'));
//包含框架入口文件
require WEBPATH . '/libs/lib_config.php';

$config = array(
    'host' => '127.0.0.1',
    'port' => '6379',
);

$pool = new SPF\Async\Redis($config, 10);

$pool->get("key", function ($redis, $result) use ($pool)
{
    echo "get key: ";
    var_dump($result);
    $redis->incr("key_hello", function ($redis, $result) use ($pool)
    {
        echo "incr key_hello: ";
        var_dump($result);
    });
});
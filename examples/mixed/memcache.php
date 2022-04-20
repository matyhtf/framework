<?php
define('DEBUG', 'on');
define('WEBPATH', realpath(__DIR__ . '/..'));
//包含框架入口文件
require WEBPATH . '/libs/lib_config.php';

$mc = new SPF\Coroutine\Memcache();
$mc->addServer('127.0.0.1', 11211);

go(function () use ($mc) {

    $res = $mc->set('key', ['aa']);
    echo "set res:".var_export($res,1)."\n";

    $res = $mc->get('key');
    echo "get res:".var_export($res,1)."\n";

    $res = $mc->set('key', [new \stdClass()]);
    echo "set res:".var_export($res,1)."\n";

    $res = $mc->get('key');
    echo "get res:".var_export($res,1)."\n";

    $res = $mc->delete('key1');
    echo "delete res:".var_export($res,1)."\n";
    $res = $mc->add('key1', 'data1', 10);
    echo "add res:".var_export($res,1)."\n";
    $res = $mc->set('key2', 'data2', 10);
    echo "set res:".var_export($res,1)."\n";
    $res = $mc->getMulti(['key1', 'key2']);
    echo "getMulti res:".var_export($res,1)."\n";
    $res = $mc->getStats();
    echo "getStats res:".var_export($res,1)."\n";
    $res = $mc->set('counter', 5);
    echo "set res:".var_export($res,1)."\n";
    $res = $mc->increment('counter', 10);
    echo "increment res:".var_export($res,1)."\n";
    $res = $mc->decrement('counter', 10);
    echo "decrement res:".var_export($res,1)."\n";

});
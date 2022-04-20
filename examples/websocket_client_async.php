<?php
define('DEBUG', 'on');
define('WEBPATH', realpath(__DIR__ . '/..'));
//包含框架入口文件
require WEBPATH . '/libs/lib_config.php';

//$cli = new SPF\Client\WebSocket('127.0.0.1', 9501);
//$res = $cli->connect();
////测试各种大包
//for($i=0; $i< 1000; $i++)
//{
//    $_send = str_repeat('A', rand(7000, 90000));
//    $n = $cli->send($_send);
//    echo "sent: ".strlen($_send).' bytes, '."n=$n\n";
//    $frame = $cli->recv();
//    echo "recv: ".strlen($frame->data)." bytes\n";
//}

$i = 0;
$cli = new SPF\Async\WebSocket('127.0.0.1', 9443);

$cli->on('open', function(SPF\Async\WebSocket $o, $header){
    $_send = str_repeat('A', rand(700, 900));
    $n = $o->send($_send);
    echo "sent: " . strlen($_send) . ' bytes, ' . "n=$n\n";
});

$cli->on('message', function(SPF\Async\WebSocket $o, $frame){
    global $i;
    echo "$i\trecv: ".strlen($frame->data)." bytes\n";
    $i++;
});

$cli->connect();
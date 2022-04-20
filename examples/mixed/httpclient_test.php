<?php
define('DEBUG', 'on');
define('WEBPATH', realpath(__DIR__ . '/..'));
//包含框架入口文件
require WEBPATH . '/libs/lib_config.php';

function test(SPF\Async\HttpClient $class, $body, $header)
{
    echo $class->id . " finish\n";
    //var_dump($header);
    $class->close();
}

for ($i = 0; $i < 10; $i++)
{
    $httpclient = new \SPF\Async\HttpClient ("http://news.163.com/15/0122/13/AGILC6J90001124J.html");
    $httpclient->id = $i;
    $httpclient->onReady("test");
    $httpclient->get();
}


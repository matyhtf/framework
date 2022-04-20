<?php
define('DEBUG', 'on');
define('WEBPATH', dirname(__DIR__));
require WEBPATH . '/libs/lib_config.php';

$client = new SPF\Async\HttpClient('http://127.0.0.1:8888/post.php');
$client->onReady(function($cli, $body, $header){
    var_dump($body, $header);
});
$client->post(array('hello' => 'world'));

$client = new SPF\Async\HttpClient('http://www.baidu.com/');
$client->onReady(function($cli, $body, $header){
    var_dump($body, $header);
});
$client->get();
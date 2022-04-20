<?php
define('DEBUG', 'on');
define('WEBPATH', realpath(__DIR__ . '/..'));
//包含框架入口文件
require WEBPATH . '/libs/lib_config.php';

$courl = new \SPF\Client\CoURL();

$ret1 = $courl->get("http://cn.bing.com/", function($ret) {
    var_dump(strlen($ret->result));
});
$ret2 = $courl->get("http://cn.bing.com/", function($ret) {
    var_dump(strlen($ret->result));
});
$ret3 = $courl->get("http://cn.bing.com/");
$ret4 = $courl->get("http://cn.bing.com/");

$courl->wait();

var_dump($ret3->info, $ret4->info);
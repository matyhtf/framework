<?php
define('DEBUG', 'on');
define('WEBPATH', realpath(__DIR__ . '/..'));
//包含框架入口文件
require WEBPATH . '/libs/lib_config.php';

$curl = new \SPF\Client\CURL(true);
$r = $curl->get("http://localhost/dump.php");
var_dump($curl->getCookies());
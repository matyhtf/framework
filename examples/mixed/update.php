<?php
define('DEBUG', 'on');
define('WEBPATH', realpath(__DIR__ . '/..'));
//包含框架入口文件
require WEBPATH . '/libs/lib_config.php';

$sdb = new SPF\SelectDB(SPF\App::$app->db);
$sdb->from('userinfo');
$sdb->where('name="rango"');
$sdb->update(['lastlogin_ip' => 2]);
$res = $sdb->rowCount();
var_dump($res);
//$result = $sdb->getall();
//var_dump($result);


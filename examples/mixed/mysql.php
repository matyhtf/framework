<?php
define('DEBUG', 'on');
define('WEBPATH', realpath(__DIR__ . '/..'));
//包含框架入口文件
require WEBPATH . '/libs/lib_config.php';

$config = array(
    'type' => SPF\Database::TYPE_MYSQLi,
    'host' => '127.0.0.1',
    'user' => 'root',
    'password' => 'root',
    'database' => 'test',
);

$db = new SPF\Database($config);
$db->connect();
$res = $db->query("select * from test");
var_dump($res);

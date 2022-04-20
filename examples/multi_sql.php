<?php
define('DEBUG', 'on');
define('WEBPATH', dirname(__DIR__));
require __DIR__ . '/../libs/lib_config.php';

define('SPLIT_LINE', str_repeat('-',120)."\n");
$client = new \SPF\Client\CoMySQL('master');

$ret1 = $client->query("show tables");
$ret2 = $client->query("desc user_login", function ($result) {
    echo SPLIT_LINE;
    $r = $result->fetchAll();
    echo "callback ".count($r)."\n";
    var_dump($r);
});
$ret3 = $client->query("desc cw_user_admire");
$ret4 = $client->query("desc userinfo");

$client->wait();

echo SPLIT_LINE,$ret1->sql,SPLIT_LINE;
var_dump($ret1->result->fetchAll());

echo SPLIT_LINE,$ret3->sql,SPLIT_LINE;
var_dump($ret3->result->fetchAll());

echo SPLIT_LINE,$ret4->sql,SPLIT_LINE;
var_dump($ret4->result->fetchAll());
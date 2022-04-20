<?php
define('DEBUG', 'on');
define('WEBPATH', realpath(__DIR__ . '/..'));
//包含框架入口文件
require WEBPATH . '/libs/lib_config.php';
//SPF::$php->db->debug = true;
//
//$rs = $php->db->query("show tables")->fetchall();
//var_dump($rs);
//
//echo "sleep 10s\n";
//sleep(10);
//
//$rs = $php->db->query("show tables")->fetchall();
//var_dump($rs);
//
//exit;

$r = SPF\App::$app->db->query("show tables")->fetchall();
var_dump($r);

$r = SPF\App::$app->db('slave')->query("show tables")->fetchall();
var_dump($r);

SPF\App::$app->unloadModule('db', 'slave');
var_dump(SPF\App::$app);
exit;

$a = model('User');
$r = $a->puts(array('username', 'mobile', 'realname'), array(
    ['rango1', '153223211', 'rango2',],
    ['rango2', '153223211', 'rango1',],
    ['rango3', '153223211', 'rango2',],
    ['rango4', '153223211', 'rango1',],
));
var_dump($r);
exit;
$r = table('user_profile')->put(array(
    //'uid' => 1234,
    'name' => 'ran\'go',
    'mobile' => '10086',
));
debug($r);

$table = table('user_login');
//
$res = $table->gets(array(
    'in' => array('id', [1, 2]),
//    'cache' => array(
////        'key' => 'user_1234',
////        'lifetime' => 180,
////        'object_id' => 'master',
//    ),
));
var_dump($res);

//$table->sets(array('password' => \SPF\Auth::makePasswordHash('test', 123456)), array(
//    'id' => 1,
//));
//var_dump($table->del(3));
//var_dump($table->getAffectedRows());

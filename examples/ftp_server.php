<?php
define('DEBUG', 'on');
define("WEBPATH", realpath(__DIR__.'/../'));
require __DIR__ . '/../libs/lib_config.php';

$ftpSvr = new SPF\Protocol\FtpServer();
$ftpSvr->users['test'] = array(
    'password' => 'test',
    'home' => '/tmp/',
    'chroot' => true,
);

//$ftpSvr->users['anonymous'] = array(
//    'password' => 'anon@localhost',
//    'home' => '/tmp/',
//    'chroot' => true,
//);

$server = SPF\Network\Server::autoCreate('0.0.0.0', 21);
$server->setProtocol($ftpSvr);
$server->run(array('worker_num' => 1));
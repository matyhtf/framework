<?php
define('DEBUG', 'on');
define("WEBPATH", realpath(__DIR__ . '/../'));
require_once __DIR__ . '/../vendor/autoload.php';

$app = SPF\App::getInstance(__DIR__);
$app->config->set('log', [
    'master' => [
        'type' => 'FileLog',
        'file' => __DIR__ . '/server.log',
    ],
]);
$queueSvr = new SPF\Protocol\QueueServer(new SPF\Queue\File(['name' => 'qtest', 'dir' => '/tmp']));

$server = SPF\Network\Server::autoCreate('0.0.0.0', 6655);
$server->setProtocol($queueSvr);
$server->run(array('worker_num' => 1));

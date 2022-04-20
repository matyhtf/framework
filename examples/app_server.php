<?php
define('DEBUG', 'on');
define('WEBPATH', realpath(__DIR__ . '/../'));
require dirname(__DIR__) . '/libs/lib_config.php';

//设置PID文件的存储路径
SPF\Network\Server::setPidFile(__DIR__ . '/app_server.pid');
/**
 * 显示Usage界面
 * php app_server.php start|stop|reload
 */
SPF\Network\Server::start(function ()
{
    $server = SPF\Protocol\WebServer::create(__DIR__ . '/swoole.ini');
    $server->setAppPath(WEBPATH . '/apps/');                                 //设置应用所在的目录
    $server->setDocumentRoot(WEBPATH);
    $server->setLogger(new SPF\Log\EchoLog(__DIR__ . "/webserver.log")); //Logger
    //$server->daemonize();                                                  //作为守护进程
    $server->run(array('worker_num' => 1, 'max_request' => 5000, 'log_file' => '/tmp/swoole.log'));
});

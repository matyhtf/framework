<?php
define('DEBUG', 'on');
require __DIR__ . '/../vendor/autoload.php';
//require __DIR__'/phar://swoole.phar';
$app = SPF\App::getInstance( realpath(__DIR__ . '/../'));

class EchoServer extends SPF\Protocol\Base
{
    function onReceive($server, $client_id, $tid, $data)
    {
        $this->server->send($client_id, "SPF: " . $data);
    }
}

//设置PID文件的存储路径
SPF\Network\Server::setPidFile(__DIR__ . '/echo_server.pid');
SPF\Network\Server::addOption('c|config:', "要加载的配置文件");

/**
 * 显示Usage界面
 * php app_server.php start|stop|reload
 */
SPF\Network\Server::start(function ($options)
{
    $AppSvr = new EchoServer();
    $listenHost = empty($options['host']) ? '0.0.0.0' : $options['host'];
    $listenPort = empty($options['port']) ? 9501 : $options['port'];
    $server = SPF\Network\Server::autoCreate($listenHost, $listenPort);
    $server->setProtocol($AppSvr);
    $server->run(array('worker_num' => 1));
});

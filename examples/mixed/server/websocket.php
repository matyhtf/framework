<?php
define('DEBUG', 'on');
require __DIR__ . '/../../vendor/autoload.php';

$app = SPF\App::getInstance(__DIR__ . '/../');

class WebSocket2 extends SPF\Protocol\WebSocket
{
    protected $message;

    /**
     * @param     $serv Swoole\Server
     * @param int $worker_id
     */
    function onStart($serv, $worker_id = 0)
    {
        SPF\App::getInstance()->router(array($this, 'router'));
        parent::onStart($serv, $worker_id);
    }

    function router()
    {
        var_dump($this->message);
    }

    /**
     * 进入
     * @param $client_id
     */
    function onEnter($client_id)
    {

    }

    /**
     * 下线时，通知所有人
     */
    function onExit($client_id)
    {
        //将下线消息发送给所有人
        //$this->log("onOffline: " . $client_id);
        //$this->broadcast($client_id, "onOffline: " . $client_id);
    }

    function onMessage_mvc($client_id, $ws)
    {
        $this->log("onMessage: " . $client_id . ' = ' . $ws['message']);

        $this->message = $ws['message'];
        $response = SPF\App::$app->handle();

        $this->send($client_id, $response);
        //$this->broadcast($client_id, $ws['message']);
    }

    /**
     * 接收到消息时
     */
    function onMessage($client_id, $ws)
    {
        $this->log("onMessage: " . $client_id . ' = ' . $ws['message']);
        $this->send($client_id, 'Server: ' . $ws['message']);
        //$this->broadcast($client_id, $ws['message']);
    }

    function broadcast($client_id, $msg)
    {
        foreach ($this->connections as $clid => $info) {
            if ($client_id != $clid) {
                $this->send($clid, $msg);
            }
        }
    }
}

SPF\Config::$debug = true;
SPF\Error::$echo_html = false;

$AppSvr = new WebSocket2();
$AppSvr->setLogger(new \SPF\Log\EchoLog(true));

$enable_ssl = false;
SPF\Network\Server::setOption('base', true);
$server = SPF\Network\Server::autoCreate('0.0.0.0', 9501, $enable_ssl);
$server->setProtocol($AppSvr);
//$server->daemonize(); //作为守护进程
$server->run(array(
    'worker_num' => 1,
));

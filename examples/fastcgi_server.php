<?php
define('DEBUG', 'on');
define("WEBPATH", realpath(__DIR__.'/../'));

require __DIR__ . '/../libs/lib_config.php';

SPF\Config::$debug = false;


SPF\Error::$echo_html = true;

class MyFastCGI extends SPF\Protocol\FastCGI
{
	function onRequest(SPF\Request $request)
	{
		$response = new SPF\Response;
		$response->body = "hello world";
		return $response;
	}
}
	
$AppSvr = new MyFastCGI();
$AppSvr->setLogger(new SPF\Log\EchoLog(true));

/**
 * 如果你没有安装swoole扩展，这里还可选择
 * BlockTCP 阻塞的TCP，支持windows平台
 * SelectTCP 使用select做事件循环，支持windows平台
 * EventTCP 使用libevent，需要安装libevent扩展
 */
$server = new SPF\Network\SelectTCP('0.0.0.0', 9001);

$server->setProtocol($AppSvr);
//$server->daemonize(); //作为守护进程
$server->run(array('worker_num' => 1, 'max_request' => 5000, 'log_file' => '/tmp/swoole.log'));

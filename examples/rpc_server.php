<?php
define('DEBUG', 'on');
define('WEBPATH', realpath(__DIR__ . '/../'));
require dirname(__DIR__) . '/libs/lib_config.php';

use  SPF\Protocol\RPCServer;


//设置PID文件的存储路径
SPF\Network\Server::setPidFile(__DIR__ . '/app_server.pid');
/**
 * 显示Usage界面
 * php app_server.php start|stop|reload
 */
SPF\Network\Server::start(function ()
{
    $AppSvr = new RPCServer;
    $AppSvr->setLogger(new SPF\Log\EchoLog(true)); //Logger

    /**
     * 注册一个自定义的命名空间到SOA服务器
     * 默认使用 apps/classes
     */
    $AppSvr->addNameSpace('BL', __DIR__ . '/class');
    /**
     * IP白名单设置
     */
    $AppSvr->addAllowIP('127.0.0.1');
    $AppSvr->addAllowIP('127.0.0.2');

    /**
     * 设置用户名密码
     */
    $AppSvr->addAllowUser('chelun', 'chelun@123456');

    SPF\Error::$echo_html = false;
    $server = SPF\Network\Server::autoCreate('0.0.0.0', 8888);
    $server->setProtocol($AppSvr);
    //$server->daemonize(); //作为守护进程
    $server->run(
        array(
            //TODO： 实际使用中必须调大进程数
            'worker_num' => 4,
            'max_request' => 5000,
            'dispatch_mode' => 3,
            'open_length_check' => 1,
            'package_max_length' => $AppSvr->packet_maxlen,
            'package_length_type' => 'N',
            'package_body_offset' => SPF\Protocol\RPCServer::HEADER_SIZE,
            'package_length_offset' => 0,
        )
    );
});


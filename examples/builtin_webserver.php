<?php
define('DEBUG', 'on');
define('WEBPATH', dirname(__DIR__));
require WEBPATH . '/libs/lib_config.php';

//设置PID文件的存储路径
SPF\Network\Server::setPidFile(__DIR__ . '/built_webserver.pid');
/**
 * 显示Usage界面
 * php app_server.php start|stop|reload
 */
SPF\Network\Server::start(function ()
{
    $config = array(
        'document_root' => WEBPATH,
//        'log_file' => '/tmp/swoole.log',
        'charset' => 'UTF-8',
    );
    SPF\App::$enableCoroutine = true;
    SPF\App::$enableOutputBuffer = false;
    SPF\App::$app->runHttpServer('0.0.0.0', 9501, $config);
});

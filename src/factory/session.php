<?php
$app = SPF\App::getInstance();
//未定义session配置，默认使用PHP session
if (empty($app->config['session'])) {
    $config = array('use_php_session' => true);
} else {
    $config = $app->config['session'];
}
//Server模式不支持php session
if (defined('SWOOLE_SERVER')) {
    $config['use_php_session'] = false;
}
//未设置session的cache
if (empty($config['cache_id'])) {
    $config['cache_id'] = 'session';
}
return new SPF\Session($config);

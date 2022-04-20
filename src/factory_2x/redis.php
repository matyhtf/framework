<?php
$app = SPF\App::getInstance();

$config = $app->config['redis'][$app->factory_key];
if (empty($config) or empty($config['host'])) {
    throw new Exception("require redis[$app->factory_key] config.");
}

if (empty($config['port'])) {
    $config['port'] = 6379;
}

if (empty($config['timeout'])) {
    $config['timeout'] = 0.5;
}

//用于隔离多实例
$config['object_id'] = $app->factory_key;

$redis = new SPF\Coroutine\Component\Redis($config);
return $redis;
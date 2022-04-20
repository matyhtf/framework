<?php
$app = SPF\App::getInstance();

$config = $app->config['redis'][$app->factory_key];
if (empty($config) or (empty($config['host']) and empty($config['socket']))) {
    throw new Exception("require redis[$app->factory_key] config.");
}

if (empty($config['port'])) {
    $config['port'] = 6379;
}

if (empty($config["pconnect"])) {
    $config["pconnect"] = false;
}

if (empty($config['timeout'])) {
    $config['timeout'] = 0.5;
}

$redis = new SPF\Component\Redis($config);
return $redis;
